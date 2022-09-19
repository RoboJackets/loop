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
        Schema::table('funding_allocation_lines', static function (Blueprint $table): void {
            $table->unsignedSmallInteger('sofo_line_number')->nullable();
            $table->string('type')->nullable();

            $table->unique(['funding_allocation_id', 'sofo_line_number', 'type'], 'funding_allocation_lines_sofo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funding_allocation_lines', static function (Blueprint $table): void {
            $table->dropIndex('funding_allocation_lines_sofo');

            $table->dropColumn('type');
            $table->dropColumn('sofo_line_number');
        });
    }
};
