<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\View;
use App\Models\Custody;
use App\Models\User;

echo "=== Testing View Rendering ===\n\n";

try {
    // Get test data like the controller does
    $custodies = Custody::with(['agent', 'treasury', 'accountant', 'transactions', 'expenses'])
        ->orderBy('created_at', 'desc')
        ->get();
    
    $acceptedCustodies = $custodies->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed']);
    $activeCustodies = $custodies->whereIn('status', ['accepted', 'active']);
    $pendingCustodies = $custodies->where('status', 'pending');
    $rejectedCustodies = $custodies->where('status', 'rejected');
    $partiallyReturnedCustodies = $custodies->where('status', 'partially_returned');
    $closedCustodies = $custodies->where('status', 'closed');
    
    $stats = [
        'total_custodies' => $custodies->count(),
        'active_custodies' => $activeCustodies->count(),
        'pending_custodies' => $pendingCustodies->count(),
        'rejected_custodies' => $rejectedCustodies->count(),
        'closed_custodies' => $closedCustodies->count(),
        'total_amount' => $acceptedCustodies->sum('amount'),
        'total_spent' => $acceptedCustodies->sum('spent'),
        'total_returned' => $acceptedCustodies->sum('returned'),
        'total_remaining' => $acceptedCustodies->sum(fn($c) => $c->getRemainingBalance()),
        'pending_returns' => $acceptedCustodies->sum('pending_return'),
        'active_amount' => $activeCustodies->sum('amount'),
        'active_spent' => $activeCustodies->sum('spent'),
        'active_remaining' => $activeCustodies->sum(fn($c) => $c->getRemainingBalance()),
        'pending_amount' => $pendingCustodies->sum('amount'),
        'rejected_amount' => $rejectedCustodies->sum('amount'),
        'rejected_spent' => $rejectedCustodies->sum('spent'),
        'partially_returned_amount' => $partiallyReturnedCustodies->sum('amount'),
        'partially_returned_spent' => $partiallyReturnedCustodies->sum('spent'),
        'partially_returned_returned' => $partiallyReturnedCustodies->sum('returned'),
    ];
    
    $agents = User::role('مندوب')->orderBy('name')->get();
    
    $agentsSummary = $activeCustodies
        ->groupBy('agent_id')
        ->map(function ($agentCustodies) {
            $agent = $agentCustodies->first()->agent;
            return [
                'agent' => $agent,
                'count' => $agentCustodies->count(),
                'total_amount' => $agentCustodies->sum('amount'),
                'total_spent' => $agentCustodies->sum('spent'),
                'total_returned' => $agentCustodies->sum('returned'),
                'total_remaining' => $agentCustodies->sum(fn($c) => $c->getRemainingBalance()),
                'custodies' => $agentCustodies->map(fn($c) => [
                    'id' => $c->id,
                    'amount' => $c->amount,
                    'spent' => $c->spent,
                    'returned' => $c->returned,
                    'remaining' => $c->getRemainingBalance(),
                    'status' => $c->status,
                    'created_at' => $c->created_at->format('Y-m-d'),
                ])->values(),
            ];
        })
        ->sortByDesc('total_remaining')
        ->values();
    
    echo "✓ Data prepared: " . $agentsSummary->count() . " agents with custodies\n";
    
    // Try to render the view
    $html = View::make('custodies.all-custodies', compact('custodies', 'stats', 'agents', 'agentsSummary'))->render();
    
    // Check for key elements
    $checks = [
        'remainingBreakdown' => 'تفصيل المتبقي لكل مندوب',
        'remainingCard' => 'اضغط لتفصيل المندوبين',
        'toggleRemainingBreakdown' => 'toggleRemainingBreakdown',
        'toggleAgentDetail' => 'toggleAgentDetail',
    ];
    
    echo "\nView Rendering Checks:\n";
    foreach ($checks as $id => $text) {
        if (strpos($html, $text) !== false) {
            echo "✓ Found: $text\n";
        } else {
            echo "✗ Missing: $text\n";
        }
    }
    
    echo "\n✅ View renders successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
