<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');           // created, updated, deleted, approved, rejected, etc.
            $table->string('subject_type');    // App\Models\Custody, Expense, etc.
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');     // human-readable Arabic description
            $table->json('properties')->nullable(); // extra data (old/new values)
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
