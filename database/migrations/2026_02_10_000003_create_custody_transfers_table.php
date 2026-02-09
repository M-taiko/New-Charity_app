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
        Schema::create('custody_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('custody_id')->constrained('custodies')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['from_agent_id', 'status']);
            $table->index(['to_agent_id', 'status']);
            $table->index('custody_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custody_transfers');
    }
};
