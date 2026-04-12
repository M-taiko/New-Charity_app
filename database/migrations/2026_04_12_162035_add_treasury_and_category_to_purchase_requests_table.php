<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            // Add treasury_id to track which treasury the purchase is paid from
            $table->foreignId('treasury_id')->nullable()->after('status')->constrained('treasuries')->onDelete('set null');

            // Add expense_category_id to categorize the purchase for accounting
            $table->foreignId('expense_category_id')->nullable()->after('treasury_id')->constrained('expense_categories')->onDelete('set null');

            // Track how amount was distributed if split across multiple treasuries
            $table->longText('treasury_distribution')->nullable()->after('expense_category_id')->comment('JSON: {treasury_id: amount, ...}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['treasury_id']);
            $table->dropForeignKeyIfExists(['expense_category_id']);
            $table->dropColumn(['treasury_id', 'expense_category_id', 'treasury_distribution']);
        });
    }
};
