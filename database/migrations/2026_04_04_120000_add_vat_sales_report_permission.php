<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Register VAT Sales Report permission (assign via Role > Permissions).
     */
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'vat-sales-report', 'guard_name' => 'web']
        );
    }

    public function down(): void
    {
        Permission::where('name', 'vat-sales-report')->delete();
    }
};
