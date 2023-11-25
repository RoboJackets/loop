<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        $create_email_requests = Permission::firstOrCreate(['name' => 'create-email-requests']);
        $update_email_requests = Permission::firstOrCreate(['name' => 'update-email-requests']);
        $delete_email_requests = Permission::firstOrCreate(['name' => 'delete-email-requests']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo($create_email_requests);
        $admin->givePermissionTo($update_email_requests);
        $admin->givePermissionTo($delete_email_requests);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        Permission::where('name', 'create-email-requests')->delete();
        Permission::where('name', 'update-email-requests')->delete();
        Permission::where('name', 'delete-email-requests')->delete();
    }
};
