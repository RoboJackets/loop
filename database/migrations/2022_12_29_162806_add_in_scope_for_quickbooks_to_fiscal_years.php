<?php

declare(strict_types=1);

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
        Schema::table('fiscal_years', static function (Blueprint $table): void {
            $table->boolean('in_scope_for_quickbooks')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_years', static function (Blueprint $table): void {
            $table->dropColumn(['in_scope_for_quickbooks']);
        });
    }
};
