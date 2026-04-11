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
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->enum('target_type', ['all', 'user'])->default('all')->after('is_active');
            $table->foreignId('target_user_id')->nullable()->after('target_type')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropForeign('broadcasts_target_user_id_foreign');
            $table->dropColumn(['target_type', 'target_user_id']);
        });
    }
};
