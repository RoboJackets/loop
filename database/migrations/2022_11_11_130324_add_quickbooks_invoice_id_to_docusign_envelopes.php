<?php

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
            $table->unsignedSmallInteger('quickbooks_invoice_id')->unique()->nullable();
            $table->unsignedSmallInteger('quickbooks_invoice_document_number')->unique()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('docusign_envelopes', static function (Blueprint $table): void {
            $table->dropColumn('quickbooks_invoice_id');
            $table->dropColumn('quickbooks_invoice_document_number');
        });
    }
};
