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
        Schema::create('expense_payments', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedMediumInteger('workday_instance_id')->unique();
            $table->string('status');
            $table->boolean('reconciled');
            $table->unsignedSmallInteger('external_committee_member_id');
            $table->date('payment_date');
            $table->decimal('amount', 8, 2);
            $table->unsignedMediumInteger('transaction_reference')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
