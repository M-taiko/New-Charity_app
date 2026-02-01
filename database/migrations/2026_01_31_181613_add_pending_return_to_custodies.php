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
            $table->decimal('pending_return', 10, 2)->default(0)->after('returned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            $table->dropColumn('pending_return');
        });
    }
};
