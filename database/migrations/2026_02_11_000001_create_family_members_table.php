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
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_case_id')->constrained('social_cases')->onDelete('cascade');
            $table->string('name');
            $table->string('relationship'); // زوج، ابن، أب، etc.
            $table->enum('gender', ['male', 'female']);
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('social_case_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
