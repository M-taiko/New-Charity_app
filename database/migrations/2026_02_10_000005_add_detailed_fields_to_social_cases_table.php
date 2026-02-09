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
            // Location and contact info
            $table->text('address')->nullable()->after('name');
            $table->string('city')->nullable()->after('address');
            $table->string('district')->nullable()->after('city');

            // Personal information
            $table->date('birth_date')->nullable()->after('district');
            $table->enum('gender', ['male', 'female'])->nullable()->after('birth_date');
            $table->enum('marital_status', ['single', 'married', 'widowed', 'divorced'])->nullable()->after('gender');

            // Family information
            $table->integer('family_members_count')->nullable()->after('marital_status');

            // Financial information
            $table->decimal('monthly_income', 12, 2)->nullable()->after('family_members_count');
            $table->decimal('monthly_expenses', 12, 2)->nullable()->after('monthly_income');

            // Health and special needs
            $table->text('health_conditions')->nullable()->after('monthly_expenses');
            $table->boolean('has_disability')->default(false)->after('health_conditions');
            $table->text('disability_description')->nullable()->after('has_disability');
            $table->text('special_needs')->nullable()->after('disability_description');

            // Request information
            $table->decimal('requested_amount', 12, 2)->nullable()->after('special_needs');
            $table->boolean('is_verified')->default(false)->after('requested_amount');

            // Indexes for filtering
            $table->index('city');
            $table->index('district');
            $table->index('marital_status');
            $table->index('has_disability');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_cases', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropIndex(['district']);
            $table->dropIndex(['marital_status']);
            $table->dropIndex(['has_disability']);
            $table->dropIndex(['is_verified']);

            $table->dropColumn([
                'address',
                'city',
                'district',
                'birth_date',
                'gender',
                'marital_status',
                'family_members_count',
                'monthly_income',
                'monthly_expenses',
                'health_conditions',
                'has_disability',
                'disability_description',
                'special_needs',
                'requested_amount',
                'is_verified'
            ]);
        });
    }
};
