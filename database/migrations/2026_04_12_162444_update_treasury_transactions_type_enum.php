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
        // For MySQL, we need to change the enum type
        \DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('donation', 'expense', 'custody_out', 'custody_return', 'transfer_in', 'transfer_out', 'purchase_request') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old enum
        \DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('donation', 'expense', 'custody_out', 'custody_return') NOT NULL");
    }
};
