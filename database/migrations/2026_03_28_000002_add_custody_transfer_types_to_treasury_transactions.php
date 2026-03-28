<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to add new transfer types
        DB::statement(
            "ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('donation', 'expense', 'custody_out', 'custody_return', 'custody_close', 'custody_transfer', 'custody_transfer_in', 'custody_transfer_out') NOT NULL"
        );

        // Update old transaction records to use new types
        // If it's a transfer with custody_transfer type, change based on context
        DB::statement(
            "UPDATE treasury_transactions
             SET type = 'custody_transfer_in'
             WHERE type = 'custody_out'
             AND custody_transfer_id IS NOT NULL
             AND description LIKE '%تحويل%'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert new transfer records to old types
        DB::statement(
            "UPDATE treasury_transactions
             SET type = 'custody_out'
             WHERE type IN ('custody_transfer_in', 'custody_transfer_out')"
        );

        // Revert the enum
        DB::statement(
            "ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('donation', 'expense', 'custody_out', 'custody_return', 'custody_close', 'custody_transfer') NOT NULL"
        );
    }
};
