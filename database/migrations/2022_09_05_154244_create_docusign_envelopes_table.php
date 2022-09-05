<?php

declare(strict_types=1);

use App\Models\DocuSignEnvelope;
use App\Models\FiscalYear;
use App\Models\User;
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
        Schema::create('docusign_envelopes', static function (Blueprint $table): void {
            $table->id();
            $table->string('envelope_id')->unique();
            $table->string('type')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 8, 2)->nullable();
            $table->foreignIdFor(User::class, 'pay_to_user_id')->nullable()->constrained('users');
            $table->string('sofo_form_filename')->unique();
            $table->string('summary_filename')->unique();
            $table->string('sensible_extraction_id')->nullable()->unique();
            $table->json('sensible_output')->nullable();
            $table->foreignIdFor(FiscalYear::class)->nullable()->constrained();
            $table->foreignIdFor(DocuSignEnvelope::class, 'replaces_docusign_envelope_id')
                ->nullable()
                ->constrained('docusign_envelopes');
            $table->boolean('lost')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docusign_envelopes');
    }
};
