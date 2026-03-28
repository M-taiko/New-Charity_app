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
        Schema::table('expenses', function (Blueprint $table) {
            // إضافة حقل approval_status لتتبع حالة المصروف
            // null = عادي / pending_edit = في انتظار موافقة على تعديل / approved = معتمد
            if (!Schema::hasColumn('expenses', 'approval_status')) {
                $table->enum('approval_status', ['pending_edit', 'approved'])
                    ->nullable()
                    ->after('source');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};
