<?php
require 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);

// Test the donation functionality
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;

// Start Laravel
require 'bootstrap/app.php';
$app = app();

// Test 1: Check if source column accepts long strings
echo "=== TEST 1: Database Column Type ===\n";
try {
    $table = DB::connection()->getDoctrineSchemaManager()->listTableDetails('treasury_transactions');
    $sourceColumn = $table->getColumn('source');
    echo "✓ Source column type: " . $sourceColumn->getType()->getName() . "\n";
    echo "✓ Column length: " . ($sourceColumn->getLength() ?? 'unlimited') . "\n";
} catch (\Exception $e) {
    echo "✗ Error checking column: " . $e->getMessage() . "\n";
}

// Test 2: Insert a donation with long source string
echo "\n=== TEST 2: Insert External Donation with Long Source ===\n";
try {
    $treasury = Treasury::first();
    if (!$treasury) {
        echo "✗ No treasury found. Creating one...\n";
        $treasury = Treasury::create(['name' => 'Test Treasury', 'balance' => 0]);
        echo "✓ Treasury created: {$treasury->name}\n";
    }
    
    $longSource = 'external: استقبال من محفظة إنستا باي - جهة خارجية معروفة';
    
    $transaction = TreasuryTransaction::create([
        'treasury_id' => $treasury->id,
        'type' => 'donation',
        'source' => $longSource,
        'amount' => 1000.00,
        'description' => 'Test external donation',
        'user_id' => 1,
        'transaction_date' => now(),
    ]);
    
    echo "✓ Donation inserted successfully\n";
    echo "✓ Source stored: {$transaction->source}\n";
    echo "✓ Amount: " . number_format($transaction->amount, 2) . " ج.م\n";
    
    // Verify it can be retrieved
    $retrieved = TreasuryTransaction::find($transaction->id);
    echo "✓ Retrieved from DB: {$retrieved->source}\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Check existing treasuries and their balances
echo "\n=== TEST 3: Treasuries and Balances ===\n";
try {
    $treasuries = Treasury::all();
    echo "✓ Total treasuries: {$treasuries->count()}\n";
    foreach ($treasuries as $t) {
        echo "  - {$t->name}: " . number_format($t->balance, 2) . " ج.م\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Test transfer logic (without actual transfer)
echo "\n=== TEST 4: Transfer Route Availability ===\n";
try {
    if (Treasury::count() >= 2) {
        echo "✓ Multiple treasuries exist: " . Treasury::count() . "\n";
        echo "✓ Transfer feature should be available\n";
    } else {
        echo "⚠ Only " . Treasury::count() . " treasury exists. Need 2+ for transfers.\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TESTS COMPLETE ===\n";
