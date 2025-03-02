<?php

declare(strict_types=1);

namespace App\Nova\Lenses;

use App\Nova\BankTransaction;
use App\Nova\ExternalCommitteeMember;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

class ExpensePaymentsReadyToSyncToQuickBooks extends Lens
{
    /**
     * The displayable name of the lens.
     *
     * @var string
     */
    public $name = 'Expense Payments Ready to Sync to QuickBooks';

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\ExpensePayment>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\ExpensePayment>
     */
    #[\Override]
    public static function query(LensRequest $request, $query): Builder
    {
        return $request->withOrdering($request->withFilters(
            $query->whereNull('quickbooks_payment_id')
                ->whereDoesntHave(
                    'expenseReports',
                    static function (Builder $query): void {
                        $query->whereHas(
                            'engagePurchaseRequests',
                            static function (Builder $query): void {
                                $query->whereNull('quickbooks_invoice_id');
                            }
                        );
                    }
                )
                ->whereHas(
                    'expenseReports',
                    static function (Builder $query): void {
                        $query->whereHas(
                            'engagePurchaseRequests',
                            static function (Builder $query): void {
                                $query->whereHas(
                                    'fiscalYear',
                                    static function (Builder $query): void {
                                        $query->where('in_scope_for_quickbooks', '=', true);
                                    }
                                );
                            }
                        );
                    }
                )
                ->whereHas(
                    'bankTransaction',
                    static function (Builder $query): void {
                        $query->whereNotNull('transaction_posted_at');
                    }
                )
                ->whereDoesntHave(
                    'payTo',
                    static function (Builder $query): void {
                        $query->whereNotNull('user_id');
                    }
                )
                ->where('status', '=', 'Complete')
                ->where('reconciled', '=', true)
        ));
    }

    /**
     * Get the fields available to the lens.
     *
     * @return array<int,\Laravel\Nova\Fields\Field>
     */
    #[\Override]
    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('Check Number', 'transaction_reference')
                ->sortable()
                ->readonly(),

            BelongsTo::make('Pay To', 'payTo', ExternalCommitteeMember::class)
                ->sortable()
                ->readonly(),

            Date::make('Payment Date')
                ->sortable()
                ->readonly(),

            Currency::make('Amount')
                ->sortable()
                ->readonly(),

            BelongsTo::make('Bank Transaction', 'bankTransaction', BankTransaction::class)
                ->sortable()
                ->readonly(),
        ];
    }

    /**
     * Get the URI key for the lens.
     */
    #[\Override]
    public function uriKey(): string
    {
        return 'ready-to-sync';
    }
}
