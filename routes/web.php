<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\CustodyController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SocialCaseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('treasury', TreasuryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('custodies', CustodyController::class);
    Route::post('/custodies/{custody}/accept', [CustodyController::class, 'accept'])->name('custodies.accept');
    Route::post('/custodies/{custody}/reject', [CustodyController::class, 'reject'])->name('custodies.reject');
    Route::post('/custodies/{custody}/return', [CustodyController::class, 'return'])->name('custodies.return');
    Route::post('/custodies/{custody}/approve-return', [CustodyController::class, 'approveReturn'])->name('custodies.approveReturn');
    Route::get('/agent/transactions', [CustodyController::class, 'agentTransactions'])->name('agent.transactions');
    Route::get('/api/agent/transactions', [CustodyController::class, 'agentTransactionsData'])->name('api.agent.transactions');
    Route::get('/api/agent/returned', [CustodyController::class, 'agentReturnedData'])->name('api.agent.returned');

    Route::resource('expenses', ExpenseController::class);
    Route::get('/my-expenses', [ExpenseController::class, 'agentExpenses'])->name('expenses.agent');
    Route::get('/api/agent-expenses', [ExpenseController::class, 'agentExpensesData'])->name('api.agent-expenses.data');

    Route::resource('social-cases', SocialCaseController::class)->names('social_cases');
    Route::post('/social-cases/{socialCase}/approve', [SocialCaseController::class, 'approve'])->name('social_cases.approve');
    Route::post('/social-cases/{socialCase}/reject', [SocialCaseController::class, 'reject'])->name('social_cases.reject');
    Route::post('/social-cases/{socialCase}/toggle-active', [SocialCaseController::class, 'toggleActive'])->name('social_cases.toggleActive');
    Route::get('/my-cases', [SocialCaseController::class, 'researcherCases'])->name('social_cases.researcher');

    Route::resource('users', UserController::class);
    Route::post('/users/{user}/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');
    Route::post('/profile/picture', [ProfileController::class, 'updateProfilePicture'])->name('profile.update-picture');

    Route::get('/reports', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/analytics/researchers', [ReportController::class, 'researcherStats'])->name('analytics.researcher');

    // DataTables APIs
    Route::get('/api/treasury-transactions', [TreasuryController::class, 'transactionsData'])->name('api.treasury.transactions');
    Route::get('/api/custodies', [CustodyController::class, 'tableData'])->name('api.custodies.data');
    Route::get('/api/expenses', [ExpenseController::class, 'tableData'])->name('api.expenses.data');
    Route::get('/api/social-cases', [SocialCaseController::class, 'tableData'])->name('api.social_cases.data');
    Route::get('/api/users', [UserController::class, 'tableData'])->name('api.users.data');

    // Notification APIs
    Route::post('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
    Route::post('/api/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.markAllAsRead');
    Route::get('/api/notifications/unread-count', [NotificationController::class, 'getUnreadCount'])->name('api.notifications.unreadCount');
});

require __DIR__.'/auth.php';
