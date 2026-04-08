<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add external_donation and expense_refund to the type enum
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM(
            'donation','expense','custody_out','custody_return','custody_close',
            'custody_transfer','custody_transfer_in','custody_transfer_out',
            'external_donation','expense_refund'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM(
            'donation','expense','custody_out','custody_return','custody_close',
            'custody_transfer','custody_transfer_in','custody_transfer_out'
        ) NOT NULL");
    }
};
