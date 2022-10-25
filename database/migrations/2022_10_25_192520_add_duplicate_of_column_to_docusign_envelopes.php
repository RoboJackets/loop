<?php

declare(strict_types=1);

use App\Models\DocuSignEnvelope;
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
            $table->foreignIdFor(DocuSignEnvelope::class, 'duplicate_of_docusign_envelope_id')
                ->nullable()
                ->constrained('docusign_envelopes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('docusign_envelopes', static function (Blueprint $table): void {
            $table->dropForeignIdFor(DocuSignEnvelope::class, 'duplicate_of_docusign_envelope_id');
        });
    }
};
