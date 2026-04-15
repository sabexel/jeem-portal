<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Register Expense VAT Report permission (assign via Role > Permissions).
     */
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'expense-vat-report', 'guard_name' => 'web']
        );
    }

    public function down(): void
    {
        Permission::where('name', 'expense-vat-report')->delete();
    }
};
