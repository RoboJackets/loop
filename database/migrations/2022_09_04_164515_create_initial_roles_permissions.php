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

        $access_nova = Permission::firstOrCreate(['name' => 'access-nova']);
        $access_horizon = Permission::firstOrCreate(['name' => 'access-horizon']);

        Permission::firstOrCreate(['name' => 'access-workday']);
        Permission::firstOrCreate(['name' => 'access-quickbooks']);
        Permission::firstOrCreate(['name' => 'access-sensible']);

        $update_user_permissions = Permission::firstOrCreate(['name' => 'update-user-permissions']);
        $update_user_tokens = Permission::firstOrCreate(['name' => 'update-user-tokens']);
        $create_users = Permission::firstOrCreate(['name' => 'create-users']);
        $create_fiscal_years = Permission::firstOrCreate(['name' => 'create-fiscal-years']);
        $update_fiscal_years = Permission::firstOrCreate(['name' => 'update-fiscal-years']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo($access_nova);
        $admin->givePermissionTo($access_horizon);
        $admin->givePermissionTo($update_user_permissions);
        $admin->givePermissionTo($update_user_tokens);
        $admin->givePermissionTo($create_users);
        $admin->givePermissionTo($create_fiscal_years);
        $admin->givePermissionTo($update_fiscal_years);

        $treasurer = Role::firstOrCreate(['name' => 'treasurer']);
        $treasurer->givePermissionTo($access_nova);
        $treasurer->givePermissionTo($create_fiscal_years);

        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->givePermissionTo($access_nova);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        Permission::where('name', 'access-nova')->delete();
        Permission::where('name', 'access-horizon')->delete();
        Permission::where('name', 'access-workday')->delete();
        Permission::where('name', 'access-quickbooks')->delete();
        Permission::where('name', 'access-sensible')->delete();
        Permission::where('name', 'update-user-permissions')->delete();
        Permission::where('name', 'update-user-tokens')->delete();
        Permission::where('name', 'create-users')->delete();
        Permission::where('name', 'create-fiscal-years')->delete();
        Permission::where('name', 'update-fiscal-years')->delete();

        Role::where('name', 'admin')->delete();
        Role::where('name', 'treasurer')->delete();
        Role::where('name', 'auditor')->delete();
    }
};
