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

        $create_funding_allocations = Permission::firstOrCreate(['name' => 'create-funding-allocations']);
        $update_funding_allocations = Permission::firstOrCreate(['name' => 'update-funding-allocations']);
        $delete_funding_allocations = Permission::firstOrCreate(['name' => 'delete-funding-allocations']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo($create_funding_allocations);
        $admin->givePermissionTo($update_funding_allocations);
        $admin->givePermissionTo($delete_funding_allocations);

        $treasurer = Role::firstOrCreate(['name' => 'treasurer']);
        $treasurer->givePermissionTo($create_funding_allocations);
        $treasurer->givePermissionTo($update_funding_allocations);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        Permission::where('name', 'create-funding-allocations')->delete();
        Permission::where('name', 'update-funding-allocations')->delete();
        Permission::where('name', 'delete-funding-allocations')->delete();
    }
};
