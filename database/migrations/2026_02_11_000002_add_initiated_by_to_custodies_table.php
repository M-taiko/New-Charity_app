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
        Schema::table('custodies', function (Blueprint $table) {
            $table->enum('initiated_by', ['agent', 'accountant'])
                  ->after('accountant_id')
                  ->nullable();
        });

        // Backfill existing records - assume all were agent requests
        DB::statement("UPDATE custodies SET initiated_by = 'agent' WHERE initiated_by IS NULL");

        // Make it non-nullable after backfill
        Schema::table('custodies', function (Blueprint $table) {
            $table->enum('initiated_by', ['agent', 'accountant'])
                  ->nullable(false)
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            $table->dropColumn('initiated_by');
        });
    }
};
