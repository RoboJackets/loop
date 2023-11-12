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
        Schema::table('engage_purchase_requests', static function (Blueprint $table): void {
            $table->string('status');
            $table->dropColumn('approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engage_purchase_requests', static function (Blueprint $table): void {
            $table->dropColumn('status');
            $table->boolean('approved');
        });
    }
};
