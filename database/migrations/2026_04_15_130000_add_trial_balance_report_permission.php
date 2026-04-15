<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Register Trial Balance Report permission (assign via Role > Permissions).
     */
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'trial-balance-report', 'guard_name' => 'web']
        );
    }

    public function down(): void
    {
        Permission::where('name', 'trial-balance-report')->delete();
    }
};
