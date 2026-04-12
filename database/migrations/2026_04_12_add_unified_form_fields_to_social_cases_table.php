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
            // Personal info fields
            if (!Schema::hasColumn('social_cases', 'nationality')) {
                $table->enum('nationality', ['egyptian', 'other'])->nullable()->after('national_id');
            }

            // Housing information
            if (!Schema::hasColumn('social_cases', 'house_type')) {
                $table->enum('house_type', ['owned', 'rented', 'borrowed', 'shelter'])->nullable()->after('address');
            }
            if (!Schema::hasColumn('social_cases', 'house_condition')) {
                $table->enum('house_condition', ['excellent', 'good', 'fair', 'poor'])->nullable()->after('house_type');
            }

            // Financial information - new fields
            if (!Schema::hasColumn('social_cases', 'income_source')) {
                $table->string('income_source')->nullable()->after('monthly_income');
            }

            // Family information - new fields
            if (!Schema::hasColumn('social_cases', 'family_composition')) {
                $table->string('family_composition')->nullable()->after('family_members_count');
            }
            if (!Schema::hasColumn('social_cases', 'children_count')) {
                $table->integer('children_count')->nullable()->after('family_composition');
            }
            if (!Schema::hasColumn('social_cases', 'disabled_count')) {
                $table->integer('disabled_count')->nullable()->after('children_count');
            }
            if (!Schema::hasColumn('social_cases', 'disability_type')) {
                $table->string('disability_type')->nullable()->after('disabled_count');
            }

            // Assistance information - new fields
            if (!Schema::hasColumn('social_cases', 'assistance_type')) {
                $table->string('assistance_type')->nullable()->after('requested_amount');
            }
            if (!Schema::hasColumn('social_cases', 'assistance_reason')) {
                $table->text('assistance_reason')->nullable()->after('assistance_type');
            }
            if (!Schema::hasColumn('social_cases', 'other_assistance')) {
                $table->string('other_assistance')->nullable()->after('assistance_reason');
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
                'nationality',
                'house_type',
                'house_condition',
                'income_source',
                'family_composition',
                'children_count',
                'disabled_count',
                'disability_type',
                'assistance_type',
                'assistance_reason',
                'other_assistance',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('social_cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
