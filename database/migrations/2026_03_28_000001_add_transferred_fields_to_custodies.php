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
            // إضافة حقل transferred_out لتتبع المبالغ المحولة من هذه العهدة لعهد أخرى
            if (!Schema::hasColumn('custodies', 'transferred_out')) {
                $table->decimal('transferred_out', 10, 2)->default(0)->after('spent');
            }

            // إضافة حقل transferred_in لتتبع المبالغ المستقبلة من عهد أخرى
            if (!Schema::hasColumn('custodies', 'transferred_in')) {
                $table->decimal('transferred_in', 10, 2)->default(0)->after('transferred_out');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            // حذف الحقول عند التراجع
            $table->dropColumn(['transferred_out', 'transferred_in']);
        });
    }
};
