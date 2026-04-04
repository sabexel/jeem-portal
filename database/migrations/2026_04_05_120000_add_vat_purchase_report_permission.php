<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Register VAT Purchase Report permission (assign via Role > Permissions).
     */
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'vat-purchase-report', 'guard_name' => 'web']
        );
    }

    public function down(): void
    {
        Permission::where('name', 'vat-purchase-report')->delete();
    }
};
