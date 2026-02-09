<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix organization_logo path by removing 'app/public/' prefix
        DB::table('settings')
            ->where('key', 'organization_logo')
            ->whereNotNull('value')
            ->where('value', 'like', '%app/public/%')
            ->update([
                'value' => DB::raw("REPLACE(value, 'app/public/', '')")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the fix (add back 'app/public/' prefix)
        DB::table('settings')
            ->where('key', 'organization_logo')
            ->whereNotNull('value')
            ->where('value', 'not like', '%app/public/%')
            ->update([
                'value' => DB::raw("CONCAT('app/public/', value)")
            ]);
    }
};
