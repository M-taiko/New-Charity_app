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
        Schema::create('chat_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->onDelete('cascade');
            $table->string('question');
            $table->json('options'); // e.g., ["Option 1", "Option 2", "Option 3"]
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_polls');
    }
};
