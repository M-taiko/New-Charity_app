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
        Schema::table('custodies', function (Blueprint $table) {
            // Add 'active' status to the enum
            $table->enum('status', ['pending', 'accepted', 'active', 'rejected', 'partially_returned', 'closed'])
                  ->change()
                  ->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            // Revert to original enum without 'active'
            $table->enum('status', ['pending', 'accepted', 'rejected', 'partially_returned', 'closed'])
                  ->change()
                  ->default('pending');
        });
    }
};
