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
        Schema::create('expense_report_lines', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('workday_line_id');
            $table->unsignedMediumInteger('expense_report_id');
            $table->decimal('amount', 8, 2);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->unique(['expense_report_id', 'workday_line_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_report_lines');
    }
};
