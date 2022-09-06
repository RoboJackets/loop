<?php

declare(strict_types=1);

use App\Models\FiscalYear;
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
        Schema::create('funding_allocations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(FiscalYear::class)->constrained();
            $table->string('sga_bill_number')->unique()->nullable();
            $table->string('type');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_allocations');
    }
};
