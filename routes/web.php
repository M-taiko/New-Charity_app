<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\CustodyController;
use App\Http\Controllers\CustodyTransferController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseItemController;
use App\Http\Controllers\SocialCaseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ExpenseEditRequestController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatPollController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SalaryController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Treasury management (multiple treasuries)
    Route::resource('treasury', TreasuryController::class); // Full CRUD
    Route::post('/treasury/{treasury}/add-donation', [TreasuryController::class, 'addDonation'])->name('treasury.add-donation');
    Route::get('/treasury/transfer/form', [TreasuryController::class, 'transfer'])->name('treasury.transfer');
    Route::post('/treasury/perform-transfer', [TreasuryController::class, 'performTransfer'])->name('treasury.perform-transfer');
    Route::resource('custodies', CustodyController::class);
    Route::post('/custodies/{custody}/accept', [CustodyController::class, 'accept'])->name('custodies.accept');
    Route::post('/custodies/{custody}/receive', [CustodyController::class, 'receive'])->name('custodies.receive');
    Route::post('/custodies/{custody}/reject', [CustodyController::class, 'reject'])->name('custodies.reject');
    Route::post('/custodies/{custody}/return', [CustodyController::class, 'return'])->name('custodies.return');
    Route::post('/custodies/{custody}/approve-return', [CustodyController::class, 'approveReturn'])->name('custodies.approveReturn');
    Route::post('/custodies/{custody}/direct-return', [CustodyController::class, 'directReturn'])->name('custodies.directReturn');
    Route::post('/custodies/{custody}/external-donation', [CustodyController::class, 'addExternalDonation'])->name('custodies.external-donation');
    Route::post('/custodies/{custody}/agent-accept', [CustodyController::class, 'agentAccept'])->name('custodies.agent-accept');
    Route::post('/custodies/{custody}/agent-reject', [CustodyController::class, 'agentReject'])->name('custodies.agent-reject');
    Route::get('/agent/transactions', [CustodyController::class, 'agentTransactions'])->name('agent.transactions');
    Route::get('/api/agent/transactions', [CustodyController::class, 'agentTransactionsData'])->name('api.agent.transactions');
    Route::get('/api/agent/returned', [CustodyController::class, 'agentReturnedData'])->name('api.agent.returned');
    Route::get('/agent/my-custodies', [CustodyController::class, 'myCustodies'])->name('agent.my-custodies');
    Route::get('/accountant/all-custodies', [CustodyController::class, 'allCustodies'])->name('accountant.all-custodies');

    Route::resource('expenses', ExpenseController::class);
    Route::get('/my-expenses', [ExpenseController::class, 'agentExpenses'])->name('expenses.agent');
    Route::get('/api/agent-expenses', [ExpenseController::class, 'agentExpensesData'])->name('api.agent-expenses.data');
    Route::get('/expenses/{expense}/download-attachment', [ExpenseController::class, 'downloadAttachment'])->name('expenses.download-attachment');
    Route::post('/expenses/{expense}/mark-reviewed', [ExpenseController::class, 'markReviewed'])->name('expenses.mark-reviewed');

    // طلبات تعديل المصروفات
    Route::get('/expenses/{expense}/edit-request', [ExpenseEditRequestController::class, 'create'])->name('expense-edit-requests.create');
    Route::post('/expenses/{expense}/edit-request', [ExpenseEditRequestController::class, 'store'])->name('expense-edit-requests.store');
    Route::get('/expense-edit-requests', [ExpenseEditRequestController::class, 'index'])->name('expense-edit-requests.index');
    Route::get('/api/expense-edit-requests', [ExpenseEditRequestController::class, 'index'])->name('api.expense-edit-requests.data');
    Route::get('/expense-edit-requests/{editRequest}', [ExpenseEditRequestController::class, 'show'])->name('expense-edit-requests.show');
    Route::post('/expense-edit-requests/{editRequest}/approve', [ExpenseEditRequestController::class, 'approve'])->name('expense-edit-requests.approve');
    Route::post('/expense-edit-requests/{editRequest}/reject', [ExpenseEditRequestController::class, 'reject'])->name('expense-edit-requests.reject');

    // Expense Items Management
    Route::resource('expense-items', ExpenseItemController::class);
    Route::get('/api/expense-items', [ExpenseItemController::class, 'data'])->name('expense-items.data');
    Route::post('/expense-items/{expenseItem}/toggle-status', [ExpenseItemController::class, 'toggleStatus'])->name('expense-items.toggle-status');

    // Custody Transfers
    Route::resource('custody-transfers', CustodyTransferController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/custody-transfers/{custodyTransfer}/approve', [CustodyTransferController::class, 'approve'])->name('custody-transfers.approve');
    Route::post('/custody-transfers/{custodyTransfer}/reject', [CustodyTransferController::class, 'reject'])->name('custody-transfers.reject');
    Route::get('/api/custody-transfers/sent', [CustodyTransferController::class, 'sentTransfersData'])->name('api.custody-transfers.sent');
    Route::get('/api/custody-transfers/received', [CustodyTransferController::class, 'receivedTransfersData'])->name('api.custody-transfers.received');
    Route::get('/api/agent/transfers', [CustodyTransferController::class, 'agentTransfersData'])->name('api.agent.transfers');

    Route::resource('social-cases', SocialCaseController::class)->names('social_cases');
    Route::post('/social-cases/{socialCase}/approve', [SocialCaseController::class, 'approve'])->name('social_cases.approve');
    Route::post('/social-cases/{socialCase}/reject', [SocialCaseController::class, 'reject'])->name('social_cases.reject');
    Route::post('/social-cases/{socialCase}/toggle-active', [SocialCaseController::class, 'toggleActive'])->name('social_cases.toggleActive');
    Route::get('/my-cases', [SocialCaseController::class, 'researcherCases'])->name('social_cases.researcher');
    Route::get('/api/social-cases/{socialCase}/family-members', [SocialCaseController::class, 'getFamilyMembers'])->name('api.social-cases.family-members');

    Route::resource('users', UserController::class);
    Route::post('/users/{user}/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/name', [ProfileController::class, 'updateName'])->name('profile.update-name');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');
    Route::post('/profile/picture', [ProfileController::class, 'updateProfilePicture'])->name('profile.update-picture');

    Route::get('/reports', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/analytics/researchers', [ReportController::class, 'researcherStats'])->name('analytics.researcher');
    Route::get('/reports/social-case-expenses', [ReportController::class, 'socialCaseExpensesReport'])->name('reports.social-case-expenses');
    Route::get('/reports/agents-balance', [ReportController::class, 'agentsBalanceReport'])->name('reports.agents-balance');
    Route::get('/reports/expense-items', [ReportController::class, 'expenseItemsReport'])->name('reports.expense-items');
    Route::get('/reports/reconciliation', [ReportController::class, 'reconciliation'])->name('reports.reconciliation');

    // Expense Category Hierarchy API
    Route::get('/api/expense-categories/roots', [ExpenseItemController::class, 'categoryRoots'])->name('api.expense-categories.roots');
    Route::get('/api/expense-categories/{category}/children', [ExpenseItemController::class, 'categoryChildren'])->name('api.expense-categories.children');
    Route::get('/api/expense-categories/{category}/items', [ExpenseItemController::class, 'categoryItems'])->name('api.expense-categories.items');
    Route::get('/api/expense-categories/{category}/ancestors', [ExpenseItemController::class, 'categoryAncestors'])->name('api.expense-categories.ancestors');
    Route::post('/expense-categories', [ExpenseItemController::class, 'storeCategory'])->name('expense-categories.store');
    Route::delete('/expense-categories/{expenseCategory}', [ExpenseItemController::class, 'destroyCategory'])->name('expense-categories.destroy');

    // DataTables APIs
    Route::get('/api/treasury/{treasury}/transactions', [TreasuryController::class, 'transactionsData'])->name('api.treasury.transactions');
    Route::get('/api/custodies', [CustodyController::class, 'tableData'])->name('api.custodies.data');
    Route::get('/api/expenses', [ExpenseController::class, 'tableData'])->name('api.expenses.data');
    Route::get('/api/social-cases', [SocialCaseController::class, 'tableData'])->name('api.social_cases.data');
    Route::get('/api/users', [UserController::class, 'tableData'])->name('api.users.data');

    // Tasks
    Route::resource('tasks', TaskController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('/tasks/{task}/comment', [TaskController::class, 'addComment'])->name('tasks.comment');
    Route::post('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::post('/tasks/{task}/delegate', [TaskController::class, 'delegate'])->name('tasks.delegate');

    // ── Procurement ──────────────────────────────────────────────────────────
    Route::resource('purchase-requests', PurchaseRequestController::class)->except(['edit', 'update']);
    Route::post('/purchase-requests/{purchaseRequest}/approve',  [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
    Route::post('/purchase-requests/{purchaseRequest}/reject',   [PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
    Route::post('/purchase-requests/{purchaseRequest}/purchased',[PurchaseRequestController::class, 'markPurchased'])->name('purchase-requests.purchased');
    Route::get('/api/purchase-requests', [PurchaseRequestController::class, 'tableData'])->name('api.purchase-requests.data');

    Route::resource('maintenance-requests', MaintenanceRequestController::class)->except(['edit', 'update']);
    Route::post('/maintenance-requests/{maintenanceRequest}/assign',  [MaintenanceRequestController::class, 'assign'])->name('maintenance-requests.assign');
    Route::post('/maintenance-requests/{maintenanceRequest}/resolve', [MaintenanceRequestController::class, 'resolve'])->name('maintenance-requests.resolve');
    Route::post('/maintenance-requests/{maintenanceRequest}/reject',  [MaintenanceRequestController::class, 'reject'])->name('maintenance-requests.reject');

    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    // ─────────────────────────────────────────────────────────────────────────

    // Group Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/api/chat/poll', [ChatController::class, 'poll'])->name('api.chat.poll');
    Route::delete('/chat/{chatMessage}', [ChatController::class, 'destroy'])->name('chat.destroy');

    // Chat Polls
    Route::post('/api/chat-polls', [ChatPollController::class, 'store'])->name('api.chat-polls.store');
    Route::post('/api/chat-polls/{poll}/vote', [ChatPollController::class, 'vote'])->name('api.chat-polls.vote');
    Route::get('/api/chat-polls/{poll}', [ChatPollController::class, 'show'])->name('api.chat-polls.show');
    Route::post('/api/chat-polls/{poll}/close', [ChatPollController::class, 'close'])->name('api.chat-polls.close');

    // Activity Log
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/my-activity', [ActivityLogController::class, 'myActivity'])->name('my-activity.index');

    // HR Module
    Route::prefix('/hr')->name('hr.')->group(function () {
        Route::get('/dashboard', [HrController::class, 'dashboard'])->name('dashboard');

        // Employees
        Route::resource('/employees', HrController::class)->only(['index', 'create', 'store', 'edit', 'update']);

        // Attendance
        Route::get('/attendance', [HrController::class, 'attendanceIndex'])->name('attendance.index');
        Route::post('/attendance', [HrController::class, 'attendanceStore'])->name('attendance.store');

        // KPI
        Route::get('/kpi', [HrController::class, 'kpiIndex'])->name('kpi.index');
        Route::post('/kpi', [HrController::class, 'kpiStore'])->name('kpi.store');

        // Salaries
        Route::get('/salaries', [SalaryController::class, 'index'])->name('salaries.index');
        Route::post('/salaries/calculate', [SalaryController::class, 'calculate'])->name('salaries.calculate');
        Route::get('/salaries/{salary}', [SalaryController::class, 'show'])->name('salaries.show');
        Route::get('/salaries/{salary}/edit', [SalaryController::class, 'edit'])->name('salaries.edit');
        Route::put('/salaries/{salary}', [SalaryController::class, 'update'])->name('salaries.update');
        Route::post('/salaries/{salary}/allowance', [SalaryController::class, 'addAllowance'])->name('salaries.allowance');
        Route::post('/salaries/{salary}/approve', [SalaryController::class, 'approve'])->name('salaries.approve');
        Route::post('/salaries/{salary}/record-expense', [SalaryController::class, 'recordExpense'])->name('salaries.record-expense');
        Route::get('/employees/{employee}/salary-history', [SalaryController::class, 'employeeHistory'])->name('salaries.history');
        Route::get('/salaries-report/export', [SalaryController::class, 'exportReport'])->name('salaries.export');
        Route::get('/api/salaries', [SalaryController::class, 'tableData'])->name('salaries.data');
    });

    // Broadcasts (urgent messages)
    Route::get('/broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
    Route::post('/broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
    Route::post('/broadcasts/{broadcast}/deactivate', [BroadcastController::class, 'deactivate'])->name('broadcasts.deactivate');
    Route::post('/broadcasts/{broadcast}/reactivate', [BroadcastController::class, 'reactivate'])->name('broadcasts.reactivate');
    Route::post('/broadcasts/dismiss', [BroadcastController::class, 'dismiss'])->name('broadcasts.dismiss');

    // Notification Routes
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/api/notifications/poll', [NotificationController::class, 'poll'])->name('api.notifications.poll');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

require __DIR__.'/auth.php';
