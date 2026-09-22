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
        Schema::table('expenses', function (Blueprint $table) {
            // Add category and item references
            $table->foreignId('expense_category_id')->nullable()->after('amount')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('expense_item_id')->nullable()->after('expense_category_id')->constrained('expense_items')->cascadeOnDelete();

            // Add source field to distinguish between custody and treasury spending
            $table->enum('source', ['custody', 'treasury'])->default('custody')->after('expense_item_id');

            // Make custody_id nullable to support direct treasury spending
            $table->foreignId('custody_id')->nullable()->change();

            // Indexes
            $table->index('expense_category_id');
            $table->index('expense_item_id');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['expense_category_id']);
            $table->dropForeignKeyIfExists(['expense_item_id']);
            $table->dropIndex(['expense_category_id']);
            $table->dropIndex(['expense_item_id']);
            $table->dropIndex(['source']);

            $table->dropColumn(['expense_category_id', 'expense_item_id', 'source']);

            // Restore custody_id as NOT nullable
            $table->foreignId('custody_id')->change();
        });
    }
};
