<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T26: تجميد مبلغ التحويل المعلق في عهدة المرسل (المهمة الوحيدة بمigration في هذه الجولة)
 * إضافة عمود فقط: additive، لا يعدل أو يعيد حساب أي صف قائم (الصفوف القائمة تأخذ 0)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            $table->decimal('pending_transfer_out', 15, 2)->default(0)->after('pending_return');
        });
    }

    public function down(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            $table->dropColumn('pending_transfer_out');
        });
    }
};
