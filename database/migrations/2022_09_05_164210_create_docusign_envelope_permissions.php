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

        $update_docusign_envelopes = Permission::firstOrCreate(['name' => 'update-docusign-envelopes']);
        $delete_docusign_envelopes = Permission::firstOrCreate(['name' => 'delete-docusign-envelopes']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo($update_docusign_envelopes);
        $admin->givePermissionTo($delete_docusign_envelopes);

        $treasurer = Role::firstOrCreate(['name' => 'treasurer']);
        $treasurer->givePermissionTo($update_docusign_envelopes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        Permission::where('name', 'update-docusign-envelopes')->delete();
        Permission::where('name', 'delete-docusign-envelopes')->delete();
    }
};
