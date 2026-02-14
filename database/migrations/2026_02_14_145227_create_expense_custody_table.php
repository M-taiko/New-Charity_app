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
        Schema::create('expense_custody', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('custody_id')->constrained('custodies')->cascadeOnDelete();
            $table->decimal('amount', 15, 2); // Amount deducted from this custody
            $table->timestamps();

            // Indexes for faster queries
            $table->index('expense_id');
            $table->index('custody_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_custody');
    }
};
