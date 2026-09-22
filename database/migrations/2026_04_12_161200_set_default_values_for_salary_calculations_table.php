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
        Schema::table('salary_calculations', function (Blueprint $table) {
            // Add default values for calculated_salary and final_salary
            $table->decimal('calculated_salary', 12, 2)->default(0)->change();
            $table->decimal('final_salary', 12, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_calculations', function (Blueprint $table) {
            // Revert to nullable if needed
            $table->decimal('calculated_salary', 12, 2)->nullable()->change();
            $table->decimal('final_salary', 12, 2)->nullable()->change();
        });
    }
};
