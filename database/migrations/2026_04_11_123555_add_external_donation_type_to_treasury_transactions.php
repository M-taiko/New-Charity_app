<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to add external_donation type
        // This allows recording donations or refunds directly to a custody
        DB::statement("ALTER TABLE treasury_transactions MODIFY type ENUM('donation', 'expense', 'custody_out', 'custody_return', 'external_donation')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert enum to original values
        DB::statement("ALTER TABLE treasury_transactions MODIFY type ENUM('donation', 'expense', 'custody_out', 'custody_return')");
    }
};
