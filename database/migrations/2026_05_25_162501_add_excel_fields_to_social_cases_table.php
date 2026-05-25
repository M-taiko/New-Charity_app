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
        Schema::table('social_cases', function (Blueprint $table) {
            // Add age column if not exists
            if (!Schema::hasColumn('social_cases', 'age')) {
                $table->integer('age')->nullable()->after('birth_date');
            }

            // Add house description
            if (!Schema::hasColumn('social_cases', 'house_description')) {
                $table->text('house_description')->nullable()->after('address');
            }

            // Add assistance required details
            if (!Schema::hasColumn('social_cases', 'assistance_required')) {
                $table->text('assistance_required')->nullable()->after('assistance_type');
            }

            // Add case type (عمليات، غارمين، مساعدة مادية، علاج مؤقت)
            if (!Schema::hasColumn('social_cases', 'case_type')) {
                $table->enum('case_type', [
                    'عمليات',
                    'غارمين',
                    'مساعدة مادية',
                    'علاج مؤقت',
                    'تعليم',
                    'أخرى'
                ])->nullable()->after('assistance_type');
            }

            // Add dates
            if (!Schema::hasColumn('social_cases', 'case_intake_date')) {
                $table->date('case_intake_date')->nullable()->after('reviewed_at');
            }

            if (!Schema::hasColumn('social_cases', 'assistance_date')) {
                $table->date('assistance_date')->nullable()->after('case_intake_date');
            }

            // Add case outcome
            if (!Schema::hasColumn('social_cases', 'case_outcome')) {
                $table->text('case_outcome')->nullable()->after('assistance_date');
            }

            // Add dependent on field
            if (!Schema::hasColumn('social_cases', 'dependent_on')) {
                $table->string('dependent_on')->nullable()->after('case_outcome');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_cases', function (Blueprint $table) {
            $columns = [
                'age',
                'house_description',
                'assistance_required',
                'case_type',
                'case_intake_date',
                'assistance_date',
                'case_outcome',
                'dependent_on'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('social_cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
