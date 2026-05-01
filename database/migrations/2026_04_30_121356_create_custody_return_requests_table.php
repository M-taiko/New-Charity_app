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
        Schema::create('custody_return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custody_id')->constrained('custodies')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade'); // المحاسب اللي قدّم الطلب
            $table->decimal('amount', 12, 2); // المبلغ المطلوب رده
            $table->text('reason')->nullable(); // سبب الرد
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null'); // المدير اللي وافق
            $table->text('approval_notes')->nullable(); // ملاحظات المدير
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custody_return_requests');
    }
};
