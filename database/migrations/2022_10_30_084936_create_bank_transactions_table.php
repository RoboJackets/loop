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
        Schema::create('bank_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->string('bank');
            $table->string('bank_transaction_id')->nullable()->unique();
            $table->string('bank_description');
            $table->string('note')->nullable();
            $table->string('transaction_reference')->nullable()->unique();
            $table->string('status')->nullable();
            $table->string('kind')->nullable();
            $table->timestamp('transaction_created_at')->nullable();
            $table->timestamp('transaction_posted_at')->nullable();
            $table->decimal('net_amount', 8, 2);
            $table->unsignedMediumInteger('check_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
