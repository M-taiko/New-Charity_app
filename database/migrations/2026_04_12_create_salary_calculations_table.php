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
        Schema::create('salary_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('base_salary', 12, 2);
            $table->integer('total_working_days')->default(30);
            $table->integer('attendance_days')->default(0);
            $table->integer('absence_days')->default(0);
            $table->integer('late_days')->default(0);
            $table->integer('leave_days')->default(0);
            $table->decimal('calculated_salary', 12, 2);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('final_salary', 12, 2);
            $table->enum('calculation_method', ['daily_rate', 'deduction_method'])->default('daily_rate');
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid'])->default('draft');
            $table->foreignId('calculated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['employee_id', 'year', 'month']);
            $table->index('status');
            $table->index('year');
            $table->index('month');
        });

        // Create table for salary deductions and bonuses
        Schema::create('salary_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_calculation_id')->constrained('salary_calculations')->onDelete('cascade');
            $table->enum('type', ['deduction', 'bonus'])->default('deduction');
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_allowances');
        Schema::dropIfExists('salary_calculations');
    }
};
