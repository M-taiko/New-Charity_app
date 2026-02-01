<?php

namespace App\Http\Controllers;

use App\Models\Treasury;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\SocialCase;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function dashboard(): View
    {
        $this->authorize('manage_treasury');

        return view('reports.dashboard');
    }

    public function researcherStats(): View
    {
        $this->authorize('manage_treasury');

        return view('analytics.researcher-stats');
    }
}
