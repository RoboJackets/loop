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
        Schema::table('docusign_envelopes', static function (Blueprint $table): void {
            $table->boolean('internal_cost_transfer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('docusign_envelopes', static function (Blueprint $table): void {
            $table->dropColumn('internal_cost_transfer');
        });
    }
};
