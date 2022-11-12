<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->longText('quickbooks_access_token')->nullable();
            $table->timestamp('quickbooks_access_token_expires_at')->nullable();
            $table->string('quickbooks_refresh_token')->nullable();
            $table->timestamp('quickbooks_refresh_token_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn([
                'quickbooks_access_token',
                'quickbooks_access_token_expires_at',
                'quickbooks_refresh_token',
                'quickbooks_refresh_token_expires_at',
            ]);
        });
    }
};
