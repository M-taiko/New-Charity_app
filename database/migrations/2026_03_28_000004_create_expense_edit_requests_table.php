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
        Schema::create('expense_edit_requests', function (Blueprint $table) {
            $table->id();

            // الروابط الأجنبية
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('requested_by'); // المستخدم الذي طلب التعديل (المندوب عادة)
            $table->unsignedBigInteger('reviewed_by')->nullable(); // المستخدم الذي راجع/وافق (المحاسب/المدير)

            // البيانات
            $table->json('original_data'); // snapshot البيانات الأصلية
            $table->json('requested_changes'); // التغييرات المطلوبة
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable(); // سبب الرفض إن وجد

            // التواريخ
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();

            // Soft Delete
            $table->softDeletes();
            $table->timestamps();

            // الفهارس والعلاقات الأجنبية
            $table->foreign('expense_id')
                ->references('id')
                ->on('expenses')
                ->onDelete('cascade');

            $table->foreign('requested_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // الفهارس
            $table->index('expense_id');
            $table->index('requested_by');
            $table->index('reviewed_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_edit_requests');
    }
};
