<?php

declare(strict_types=1);

use App\Models\BankTransaction;
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
        Schema::table('expense_payments', static function (Blueprint $table): void {
            $table->foreignIdFor(BankTransaction::class)->nullable()->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_payments', static function (Blueprint $table): void {
            $table->dropForeignIdFor(BankTransaction::class);
            $table->dropColumn('bank_transaction_id');
        });
    }
};
