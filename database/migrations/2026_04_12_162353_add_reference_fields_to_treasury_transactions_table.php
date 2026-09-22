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
            // Reference to related entities (purchase_request, etc.)
            $table->unsignedBigInteger('reference_id')->nullable()->after('custody_id');
            $table->string('reference_type')->nullable()->after('reference_id')->comment('purchase_request, expense, etc.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropColumn(['reference_id', 'reference_type']);
        });
    }
};
