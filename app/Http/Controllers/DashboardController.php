<?php

namespace App\Http\Controllers;

use App\Models\Treasury;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\SocialCase;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $treasury = Treasury::first();
        $activeCustodies = Custody::where('status', 'accepted')->count();
        $pendingCases = SocialCase::where('status', 'pending')->count();
        $todayExpenses = Expense::whereDate('created_at', today())->sum('amount');
        $totalSpent = Expense::sum('amount');

        return view('dashboard.modern', compact(
            'treasury',
            'activeCustodies',
            'pendingCases',
            'todayExpenses',
            'totalSpent'
        ));
    }
}
