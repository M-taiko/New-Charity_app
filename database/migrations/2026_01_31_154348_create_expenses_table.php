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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custody_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('social_case_id')->nullable();
            $table->enum('type', ['social_case', 'general']);
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->string('location')->nullable();
            $table->timestamp('expense_date')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('custody_id')->references('id')->on('custodies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
