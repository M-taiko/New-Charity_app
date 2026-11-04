<?php

/**
 * The versioned JSON API for the SPA (plan section 7).
 *
 * - Reads come from the Api\V1 controllers with the shared list contract
 *   {data, meta{page,per_page,total,last_page,summary}, links}.
 * - Actions delegate to the EXISTING controllers so every business rule
 *   (T1–T36) keeps a single implementation. The ApiV1Response middleware
 *   turns their redirect+flash answers into {message, level} JSON.
 * - Session cookie authentication, same as Blade. Angular's XSRF header
 *   satisfies CSRF. 401 (expired session) is handled centrally by the SPA.
 */

use App\Http\Controllers\Api\V1\AuthController as ApiAuth;
use App\Http\Controllers\Api\V1\CustodyController as ApiCustody;
use App\Http\Controllers\Api\V1\CustodyTransferController as ApiTransfer;
use App\Http\Controllers\Api\V1\DashboardController as ApiDashboard;
use App\Http\Controllers\Api\V1\ExpenseController as ApiExpense;
use App\Http\Controllers\Api\V1\ExpenseItemController as ApiDirections;
use App\Http\Controllers\Api\V1\HrController as ApiHr;
use App\Http\Controllers\Api\V1\LookupController as ApiLookup;
use App\Http\Controllers\Api\V1\ProcurementController as ApiProcurement;
use App\Http\Controllers\Api\V1\ReportController as ApiReport;
use App\Http\Controllers\Api\V1\SocialCaseController as ApiCases;
use App\Http\Controllers\Api\V1\SystemController as ApiSystem;
use App\Http\Controllers\Api\V1\TreasuryController as ApiTreasury;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatPollController;
use App\Http\Controllers\CustodyController;
use App\Http\Controllers\CustodyMovementController;
use App\Http\Controllers\CustodyReturnRequestController;
use App\Http\Controllers\CustodyTransferController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseEditRequestController;
use App\Http\Controllers\ExpenseItemController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SocialCaseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ApiV1Response;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['web', 'throttle:' . config('api.throttle_max') . ',1'])->group(function () {

    // ── Auth (guest-accessible) ───────────────────────────────
    Route::post('auth/login', [ApiAuth::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth')->group(function () {
        Route::post('auth/logout', [ApiAuth::class, 'logout']);
        Route::get('me', [ApiAuth::class, 'me']);
        Route::get('lookups', [ApiLookup::class, 'index']);

        // Actions on the old controllers share the JSON converter middleware.
        Route::middleware(ApiV1Response::class)->group(function () {

            // ── Treasury ───────────────────────────────────────
            Route::get('treasury', [ApiTreasury::class, 'index']);
            Route::post('treasury', [TreasuryController::class, 'store']);
            Route::get('treasury/transfer/form', fn () => response()->json(['data' => []]));
            Route::get('treasury/{treasury}', [ApiTreasury::class, 'show']);
            Route::post('treasury/{treasury}/add-donation', [TreasuryController::class, 'addDonation']);
            Route::post('treasury/{treasury}/update', [TreasuryController::class, 'update']);
            Route::post('treasury/{treasury}/delete', [TreasuryController::class, 'destroy']);
            Route::post('treasury/perform-transfer', [TreasuryController::class, 'performTransfer']);

            // ── Custodies ──────────────────────────────────────
            Route::get('custodies', [ApiCustody::class, 'index']);
            Route::post('custodies', [CustodyController::class, 'store']);
            Route::get('custodies/mine', [ApiCustody::class, 'mine']);
            Route::get('custodies/tracking', [ApiCustody::class, 'tracking']);
            Route::get('custodies/movements', [ApiCustody::class, 'movements']);
            Route::get('custodies/return-requests', [ApiCustody::class, 'returnRequests']);
            Route::get('custodies/{custody}', [ApiCustody::class, 'show']);
            Route::post('custodies/{custody}/update', [CustodyController::class, 'update']);
            Route::post('custodies/{custody}/delete', [CustodyController::class, 'destroy']);
            Route::post('custodies/{custody}/accept', [CustodyController::class, 'accept']);
            Route::post('custodies/{custody}/receive', [CustodyController::class, 'receive']);
            Route::post('custodies/{custody}/reject', [CustodyController::class, 'reject']);
            Route::post('custodies/{custody}/return', [CustodyController::class, 'return']);
            Route::post('custodies/{custody}/request-return', [CustodyController::class, 'requestReturn']);
            Route::post('custodies/add-recovery', [CustodyController::class, 'addRecovery']);
            Route::post('custodies/request-return', [CustodyController::class, 'requestReturnTreasury']);
            Route::post('custodies/{custody}/external-donation', [CustodyController::class, 'addExternalDonation']);
            Route::post('custodies/{custody}/agent-accept', [CustodyController::class, 'agentAccept']);
            Route::post('custodies/{custody}/agent-reject', [CustodyController::class, 'agentReject']);
            Route::post('custodies/{custody}/cancel', [CustodyController::class, 'cancel']);
            Route::post('my-external-receipt', [CustodyController::class, 'storeExternalReceipt']);
            Route::get('agent/transactions', [ApiCustody::class, 'agentTransactions']);
            Route::get('user-custodies', [ApiCustody::class, 'userCustodies']);
            Route::post('custody-return-requests/{returnRequest}/approve', [CustodyController::class, 'approveReturnRequest']);
            Route::post('custody-return-requests/{returnRequest}/approve-with-treasury', [CustodyController::class, 'approveReturnRequestWithTreasury']);
            Route::post('custody-return-requests/{returnRequest}/reject', [CustodyController::class, 'rejectReturnRequest']);

            // ── Transfers ──────────────────────────────────────
            Route::get('custody-transfers', [ApiTransfer::class, 'index']);
            Route::post('custody-transfers', [CustodyTransferController::class, 'store']);
            Route::get('custody-transfers/{custodyTransfer}', [ApiTransfer::class, 'show']);
            Route::post('custody-transfers/{custodyTransfer}/approve', [CustodyTransferController::class, 'approve']);
            Route::post('custody-transfers/{custodyTransfer}/reject', [CustodyTransferController::class, 'reject']);

            // ── Expenses ───────────────────────────────────────
            Route::get('expenses', [ApiExpense::class, 'index']);
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::get('expenses/quick', fn () => response()->json(['data' => []]));
            Route::post('expenses/quick-store', [ExpenseController::class, 'quickStore']);
            Route::get('expenses/edit-requests', [ApiExpense::class, 'editRequests']);
            Route::get('expenses/edit-requests/{editRequest}', [ApiExpense::class, 'editRequestShow']);
            Route::post('expenses/edit-requests/{editRequest}/approve', [ExpenseEditRequestController::class, 'approve']);
            Route::post('expenses/edit-requests/{editRequest}/reject', [ExpenseEditRequestController::class, 'reject']);
            Route::get('my-expenses', [ApiExpense::class, 'mine']);
            Route::get('expenses/{expense}', [ApiExpense::class, 'show']);
            Route::post('expenses/{expense}/update', [ExpenseController::class, 'update']);
            Route::post('expenses/{expense}/delete', [ExpenseController::class, 'destroy']);
            Route::post('expenses/{expense}/mark-reviewed', [ExpenseController::class, 'markReviewed']);
            Route::post('expenses/{expense}/unreview', [ExpenseController::class, 'unreview']);
            Route::post('expenses/{expense}/edit-request', [ExpenseEditRequestController::class, 'store']);
            Route::get('expenses/{expense}/download-attachment', [ExpenseController::class, 'downloadAttachment'])
                ->withoutMiddleware(ApiV1Response::class);

            // ── Directions (expense items) ─────────────────────
            // T5-08 (ADR 0007): the lazy per-node reads (roots|children|items|
            // ancestors|{id}) are gone from the JSON contract — the SPA loads
            // the full tree via /expense-items and /lookups in one call.
            Route::get('expense-items', [ApiDirections::class, 'index']);
            Route::post('expense-items', [ExpenseItemController::class, 'store']);
            Route::post('expense-categories', [ExpenseItemController::class, 'storeCategory']);
            Route::post('expense-categories/{expenseCategory}/update', [ExpenseItemController::class, 'updateCategory']);
            Route::post('expense-categories/{expenseCategory}/delete', [ExpenseItemController::class, 'destroyCategory']);
            Route::post('expense-items/{expenseItem}/update', [ExpenseItemController::class, 'update']);
            Route::post('expense-items/{expenseItem}/delete', [ExpenseItemController::class, 'destroy']);
            Route::post('expense-items/{expenseItem}/toggle-status', [ExpenseItemController::class, 'toggleStatus']);

            // ── Reports ────────────────────────────────────────
            Route::get('dashboard', [ApiDashboard::class, 'show']);
            Route::get('directional-stats', [ApiDashboard::class, 'directionalStats']);
            Route::get('reports', [ApiReport::class, 'dashboard']);
            Route::get('reports/governorates', [ApiReport::class, 'governorates']);
            Route::get('analytics/researchers', [ApiReport::class, 'researchers']);
            Route::get('reports/social-case-expenses', [ApiReport::class, 'socialCaseExpenses']);
            Route::get('reports/agents-balance', [ApiReport::class, 'agentsBalance']);
            Route::get('reports/expense-items', [ApiReport::class, 'expenseItems']);
            Route::get('reports/categories-analytics', [ApiReport::class, 'categoriesAnalytics']);
            Route::get('reports/reconciliation', [ApiReport::class, 'reconciliation']);
            Route::get('category-item-expenses', [ReportController::class, 'categoryItemExpenses']);

            // ── Social cases ───────────────────────────────────
            Route::get('social-cases', [ApiCases::class, 'index']);
            Route::post('social-cases', [SocialCaseController::class, 'store']);
            Route::get('my-cases', [ApiCases::class, 'mine']);
            Route::get('social-cases/{socialCase}', [ApiCases::class, 'show']);
            Route::post('social-cases/{socialCase}/update', [SocialCaseController::class, 'update']);
            Route::post('social-cases/{socialCase}/delete', [SocialCaseController::class, 'destroy']);
            Route::post('social-cases/{socialCase}/approve', [SocialCaseController::class, 'approve']);
            Route::post('social-cases/{socialCase}/reject', [SocialCaseController::class, 'reject']);
            Route::post('social-cases/{socialCase}/toggle-active', [SocialCaseController::class, 'toggleActive']);
            Route::get('social-cases/{socialCase}/family-members', [SocialCaseController::class, 'getFamilyMembers']);
            Route::post('case-documents/{document}/delete', [SocialCaseController::class, 'removeDocument']);

            // ── HR ─────────────────────────────────────────────
            Route::get('hr/dashboard', [ApiHr::class, 'dashboard']);
            Route::get('hr/employees', [ApiHr::class, 'employees']);
            Route::post('hr/employees', [HrController::class, 'store']);
            Route::post('hr/employees/{id}/update', [HrController::class, 'update']);
            Route::get('hr/attendance', [ApiHr::class, 'attendance']);
            Route::post('hr/attendance', [HrController::class, 'attendanceStore']);
            Route::get('hr/kpi', [ApiHr::class, 'kpi']);
            Route::post('hr/kpi', [HrController::class, 'kpiStore']);
            Route::get('hr/salaries', [ApiHr::class, 'salaries']);
            Route::post('hr/salaries/calculate', [SalaryController::class, 'calculate']);
            Route::get('hr/salaries-report/export', [SalaryController::class, 'exportReport'])
                ->withoutMiddleware(ApiV1Response::class);
            Route::get('hr/employees/{employee}/salary-history', [ApiHr::class, 'salaryHistory']);
            Route::get('hr/salaries/{salary}', [ApiHr::class, 'salaryShow']);
            Route::post('hr/salaries/{salary}/update', [SalaryController::class, 'update']);
            Route::post('hr/salaries/{salary}/allowance', [SalaryController::class, 'addAllowance']);
            Route::post('hr/salaries/{salary}/approve', [SalaryController::class, 'approve']);
            Route::post('hr/salaries/{salary}/record-expense', [SalaryController::class, 'recordExpense']);

            // ── Procurement ────────────────────────────────────
            Route::get('purchase-requests', [ApiProcurement::class, 'purchaseRequests']);
            Route::post('purchase-requests', [PurchaseRequestController::class, 'store']);
            Route::get('purchase-requests/{purchaseRequest}', [ApiProcurement::class, 'purchaseShow']);
            Route::post('purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve']);
            Route::post('purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject']);
            Route::post('purchase-requests/{purchaseRequest}/purchased', [PurchaseRequestController::class, 'markPurchased']);
            Route::post('purchase-requests/{purchaseRequest}/delete', [PurchaseRequestController::class, 'destroy']);
            Route::get('maintenance-requests', [ApiProcurement::class, 'maintenanceRequests']);
            Route::post('maintenance-requests', [MaintenanceRequestController::class, 'store']);
            Route::get('maintenance-requests/{maintenanceRequest}', [ApiProcurement::class, 'maintenanceShow']);
            Route::post('maintenance-requests/{maintenanceRequest}/assign', [MaintenanceRequestController::class, 'assign']);
            Route::post('maintenance-requests/{maintenanceRequest}/resolve', [MaintenanceRequestController::class, 'resolve']);
            Route::post('maintenance-requests/{maintenanceRequest}/reject', [MaintenanceRequestController::class, 'reject']);
            Route::post('maintenance-requests/{maintenanceRequest}/delete', [MaintenanceRequestController::class, 'destroy']);
            Route::get('suppliers', [ApiProcurement::class, 'suppliers']);
            Route::post('suppliers', [SupplierController::class, 'store']);
            // T5-08 (ADR 0007): no suppliers/{id}/update here — the SPA has no
            // supplier-edit dialog; the Blade resource route keeps the method.
            Route::post('suppliers/{id}/delete', [SupplierController::class, 'destroy']);

            // ── Tasks ──────────────────────────────────────────
            Route::get('tasks', [ApiSystem::class, 'tasks']);
            Route::post('tasks', [TaskController::class, 'store']);
            Route::get('tasks/{task}', [ApiSystem::class, 'taskShow']);
            Route::post('tasks/{task}/comment', [TaskController::class, 'addComment']);
            Route::post('tasks/{task}/status', [TaskController::class, 'updateStatus']);
            Route::post('tasks/{task}/delegate', [TaskController::class, 'delegate']);
            Route::post('tasks/{task}/delete', [TaskController::class, 'destroy']);

            // ── Chat and polls ─────────────────────────────────
            Route::get('chat', [ApiSystem::class, 'chat']);
            Route::post('chat', [ChatController::class, 'store']);
            Route::get('chat/poll', [ChatController::class, 'poll']);
            Route::post('chat/{chatMessage}/delete', [ChatController::class, 'destroy']);
            Route::post('chat-polls', [ChatPollController::class, 'store']);
            Route::post('chat-polls/{poll}/vote', [ChatPollController::class, 'vote']);
            Route::get('chat-polls/{poll}', [ChatPollController::class, 'show']);
            Route::post('chat-polls/{poll}/close', [ChatPollController::class, 'close']);

            // ── Notifications, broadcasts, activity ────────────
            Route::get('notifications', [ApiSystem::class, 'notifications']);
            Route::get('notifications/poll', [NotificationController::class, 'poll']);
            Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::get('broadcasts', [ApiSystem::class, 'broadcasts']);
            Route::post('broadcasts', [BroadcastController::class, 'store']);
            Route::post('broadcasts/active', [ApiSystem::class, 'activeBroadcast']);
            Route::post('broadcasts/{broadcast}/deactivate', [BroadcastController::class, 'deactivate']);
            Route::post('broadcasts/{broadcast}/reactivate', [BroadcastController::class, 'reactivate']);
            Route::post('broadcasts/dismiss', [BroadcastController::class, 'dismiss']);
            Route::get('activity-logs', [ApiSystem::class, 'activityLogs']);
            Route::get('my-activity', [ApiSystem::class, 'activityLogs']);

            // ── Users, settings, profile ───────────────────────
            Route::get('users', [ApiSystem::class, 'users']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{user}', [ApiSystem::class, 'userShow']);
            Route::post('users/{user}/update', [UserController::class, 'update']);
            Route::post('users/{user}/delete', [UserController::class, 'destroy']);
            Route::post('users/{user}/assign-roles', [UserController::class, 'assignRoles']);
            Route::get('settings', [ApiSystem::class, 'settings']);
            Route::post('settings', [SettingsController::class, 'update']);
            Route::get('profile', [ApiSystem::class, 'profile']);
            Route::post('profile/name', [ProfileController::class, 'updateName']);
            Route::post('profile/password', [ProfileController::class, 'updatePassword']);
            Route::post('profile/picture', [ProfileController::class, 'updateProfilePicture']);
        });
    });
});
