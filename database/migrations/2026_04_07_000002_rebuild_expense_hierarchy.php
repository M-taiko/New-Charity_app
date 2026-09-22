<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable FK checks for safe truncate & restructure
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Null out expense references before clearing categories
        DB::table('expenses')->update(['expense_category_id' => null, 'expense_item_id' => null]);

        // Truncate old data
        DB::table('expense_items')->truncate();
        DB::table('expense_categories')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Add parent_id + level to expense_categories (skip if already exist)
        Schema::table('expense_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_categories', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                $table->foreign('parent_id')->references('id')->on('expense_categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('expense_categories', 'level')) {
                $table->tinyInteger('level')->default(1)->after('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'level']);
        });
    }
};
