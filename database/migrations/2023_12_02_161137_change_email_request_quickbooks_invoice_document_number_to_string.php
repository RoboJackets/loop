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
        Schema::table('email_requests', function (Blueprint $table) {
            $table->string('quickbooks_invoice_document_number')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('quickbooks_invoice_document_number')->change();
        });
    }
};
