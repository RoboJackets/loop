<?php

declare(strict_types=1);

use App\Models\ExpenseReport;
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
        Schema::create('engage_purchase_requests', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedMediumInteger('engage_id')->unique();
            $table->unsignedSmallInteger('engage_request_number')->unique();
            $table->string('subject');
            $table->longText('description')->nullable();
            $table->boolean('approved');
            $table->string('current_step_name');
            $table->decimal('submitted_amount', 8, 2);
            $table->timestampTz('submitted_at');
            $table->foreignIdFor(User::class, 'submitted_by_user_id')->nullable()->constrained('users');
            $table->decimal('approved_amount', 8, 2)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->constrained('users');
            $table->foreignIdFor(User::class, 'payee_user_id')->nullable()->constrained('users');
            $table->string('payee_first_name')->nullable();
            $table->string('payee_last_name')->nullable();
            $table->string('payee_address_line_one')->nullable();
            $table->string('payee_address_line_two')->nullable();
            $table->string('payee_city')->nullable();
            $table->string('payee_state')->nullable();
            $table->string('payee_zip_code')->nullable();
            $table->foreignIdFor(ExpenseReport::class)->nullable()->constrained();
            $table->unsignedSmallInteger('quickbooks_invoice_id')->nullable();
            $table->unsignedSmallInteger('quickbooks_invoice_document_number')->nullable();
            $table->foreignIdFor(FiscalYear::class)->constrained();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engage_purchase_requests');
    }
};
