<?php

declare(strict_types=1);

use App\Models\ExpenseReport;
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
        Schema::create('email_requests', static function (Blueprint $table): void {
            $table->id();
            $table->string('vendor_name')->nullable();
            $table->decimal('vendor_document_amount', 8, 2)->nullable();
            $table->string('vendor_document_reference')->nullable()->unique();
            $table->date('vendor_document_date')->nullable();
            $table->string('vendor_document_filename')->nullable()->unique();
            $table->uuid('sensible_extraction_uuid')->nullable()->unique();
            $table->json('sensible_output')->nullable();
            $table->foreignIdFor(ExpenseReport::class)->nullable()->constrained();
            $table->unsignedSmallInteger('quickbooks_invoice_id')->nullable();
            $table->unsignedSmallInteger('quickbooks_invoice_document_number')->nullable();
            $table->foreignIdFor(FiscalYear::class)->constrained();
            $table->timestampTz('email_sent_at');
            $table->timestamps();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_requests');
    }
};
