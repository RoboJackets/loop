<?php

declare(strict_types=1);

use App\Models\DocuSignEnvelope;
use App\Models\FundingAllocationLine;
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
        Schema::create('docusign_funding_sources', static function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(DocuSignEnvelope::class)->constrained();
            $table->foreignIdFor(FundingAllocationLine::class)->constrained();
            $table->decimal('amount', 8, 2);
            $table->timestamps();

            $table->unique(
                ['docusign_envelope_id', 'funding_allocation_line_id'],
                'envelope_id_allocation_line_id_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docusign_funding_sources');
    }
};
