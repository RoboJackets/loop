<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore

namespace App\Jobs;

use App\Models\BankTransaction;
use App\Models\ExpensePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchBankTransaction implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly BankTransaction $bankTransaction)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->bankTransaction->status === 'failed') {
            ExpensePayment::whereBankTransactionId($this->bankTransaction->id)
                ->update(['bank_transaction_id' => null]);
        } else {
            if ($this->bankTransaction->check_number !== null) {
                $expense_payment = ExpensePayment::whereTransactionReference($this->bankTransaction->check_number)
                    ->whereHas('payTo', static function (Builder $query): void {
                        $query->whereNull('user_id');
                    })
                    ->sole();

                $expense_payment->bank_transaction_id = $this->bankTransaction->id;
                $expense_payment->save();
            } else {
                try {
                    $expense_payment = ExpensePayment::whereAmount($this->bankTransaction->net_amount)
                        ->whereHas('payTo', static function (Builder $query): void {
                            $query->whereNull('user_id');
                        })
                        ->whereDoesntHave('bankTransaction')
                        ->whereDate(
                            'payment_date',
                            '<=',
                            $this->bankTransaction->transaction_created_at ??
                                $this->bankTransaction->transaction_posted_at
                        )
                        ->sole();

                    $expense_payment->bank_transaction_id = $this->bankTransaction->id;
                    $expense_payment->save();
                } catch (ModelNotFoundException|MultipleRecordsFoundException) {
                    // nothing to do here, human will need to match manually
                }
            }
        }
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return strval($this->bankTransaction->id);
    }
}
