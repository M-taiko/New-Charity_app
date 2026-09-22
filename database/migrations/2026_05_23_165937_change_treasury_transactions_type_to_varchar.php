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
        // Change type column from enum to varchar
        \DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type VARCHAR(50) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to enum if needed
        \DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('donation', 'expense', 'custody_out', 'custody_return', 'custody_close', 'transfer_in', 'transfer_out', 'purchase_request', 'custody_transfer_in', 'custody_transfer_out', 'recovery') NOT NULL");
    }
};
