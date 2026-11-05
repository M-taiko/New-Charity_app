<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task6 T6-80: the sender (or a manager) can cancel a pending transfer.
 * Adds `cancelled` to the status enum plus who cancelled and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE custody_transfers MODIFY status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('custody_transfers', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('custody_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });

        DB::statement("ALTER TABLE custody_transfers MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};
