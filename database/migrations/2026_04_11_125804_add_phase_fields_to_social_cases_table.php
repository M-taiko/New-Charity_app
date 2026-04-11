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
        Schema::table('social_cases', function (Blueprint $table) {
            $table->tinyInteger('phase')->default(1)->after('id');
            $table->string('affiliated_to')->nullable()->after('phase');
            $table->enum('case_intake_status', ['searched_by_phone', 'completed_externally', 'needs_research'])->nullable()->after('affiliated_to');
            $table->enum('nationality', ['egyptian', 'other'])->nullable()->after('national_id');
            $table->enum('id_type', ['national_id', 'passport'])->nullable()->after('nationality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_cases', function (Blueprint $table) {
            $table->dropColumn(['phase', 'affiliated_to', 'case_intake_status', 'nationality', 'id_type']);
        });
    }
};
