<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Expense;
use App\Models\Custody;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Add treasury_id if not exists
            if (!Schema::hasColumn('expenses', 'treasury_id')) {
                $table->unsignedBigInteger('treasury_id')->nullable()->after('user_id');
                $table->foreign('treasury_id')->references('id')->on('treasuries')->onDelete('set null');
            }
        });

        // Backfill: Set treasury_id from custody for existing expenses
        $expenses = Expense::whereNotNull('custody_id')->get();
        foreach ($expenses as $expense) {
            $custody = Custody::find($expense->custody_id);
            if ($custody) {
                $expense->update(['treasury_id' => $custody->treasury_id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeignKey(['treasury_id']);
            $table->dropColumn('treasury_id');
        });
    }
};
