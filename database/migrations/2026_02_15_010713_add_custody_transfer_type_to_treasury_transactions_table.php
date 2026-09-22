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
        // Modify the enum to add 'custody_transfer' type
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('donation', 'expense', 'custody_out', 'custody_return', 'custody_close', 'custody_transfer') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update existing custody_transfer records to expense before reverting
        DB::statement("UPDATE treasury_transactions SET type = 'expense' WHERE type = 'custody_transfer'");
        // Revert back to previous enum
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('donation', 'expense', 'custody_out', 'custody_return', 'custody_close') NOT NULL");
    }
};
