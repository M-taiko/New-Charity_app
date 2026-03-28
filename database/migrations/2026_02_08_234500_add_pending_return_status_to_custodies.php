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
        // Modify ENUM to include 'pending_return'
        DB::statement("ALTER TABLE custodies MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'pending_return', 'partially_returned', 'closed') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'pending_return' from ENUM
        DB::statement("ALTER TABLE custodies MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'partially_returned', 'closed') DEFAULT 'pending'");
    }
};
