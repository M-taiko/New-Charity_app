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
        Schema::table('treasury_transactions', function (Blueprint $table) {
            // Link to expense for detailed tracking
            $table->foreignId('expense_id')->nullable()->after('id')->constrained('expenses')->cascadeOnDelete();

            // Link to expense category and item
            $table->foreignId('expense_category_id')->nullable()->after('expense_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('expense_item_id')->nullable()->after('expense_category_id')->constrained('expense_items')->cascadeOnDelete();

            // Link to custody transfer
            $table->foreignId('custody_transfer_id')->nullable()->after('expense_item_id')->constrained('custody_transfers')->cascadeOnDelete();

            // Indexes for filtering and reporting
            $table->index('expense_id');
            $table->index('expense_category_id');
            $table->index('expense_item_id');
            $table->index('custody_transfer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['expense_id']);
            $table->dropForeignKeyIfExists(['expense_category_id']);
            $table->dropForeignKeyIfExists(['expense_item_id']);
            $table->dropForeignKeyIfExists(['custody_transfer_id']);

            $table->dropIndex(['expense_id']);
            $table->dropIndex(['expense_category_id']);
            $table->dropIndex(['expense_item_id']);
            $table->dropIndex(['custody_transfer_id']);

            $table->dropColumn([
                'expense_id',
                'expense_category_id',
                'expense_item_id',
                'custody_transfer_id'
            ]);
        });
    }
};
