<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\MatchBankTransaction;
use App\Models\BankTransaction;
use App\Models\ExpensePayment;

class BankTransactionObserver
{
    public function saved(BankTransaction $transaction): void
    {
        if (
            $transaction->net_amount > 0 &&
            ExpensePayment::whereBankTransactionId($transaction->id)->doesntExist() &&
            (
                $transaction->kind === null || $transaction->kind === 'checkDeposit'
            )
        ) {
            MatchBankTransaction::dispatch($transaction);
        }
    }
}
