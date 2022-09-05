<?php

declare(strict_types=1);

use App\Models\FundingAllocation;
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
        Schema::create('funding_allocation_lines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(FundingAllocation::class)->constrained();
            $table->unsignedSmallInteger('line_number');
            $table->string('description');
            $table->decimal('amount', 8, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['funding_allocation_id', 'line_number'], 'funding_allocation_lines_id_line_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_allocation_lines');
    }
};
