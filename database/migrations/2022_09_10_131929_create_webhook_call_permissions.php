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

        $view_webhook_calls = Permission::firstOrCreate(['name' => 'view-webhook-calls']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo($view_webhook_calls);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        Permission::where('name', 'view-webhook-calls')->delete();
    }
};
