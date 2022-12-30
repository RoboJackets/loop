<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Nova;

use App\Nova\Actions\MatchBankTransaction;
use App\Nova\Actions\RefreshMercuryTransactions;
use App\Nova\Actions\UploadBankStatement;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for bank transactions.
 *
 * @extends \App\Nova\Resource<\App\Models\BankTransaction>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class BankTransaction extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\BankTransaction::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array<string>
     */
    public static $search = [
        'id',
    ];

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Banking';

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()
                ->sortable(),

            Select::make('Bank')
                ->options(\App\Models\BankTransaction::$banks)
                ->displayUsingLabels()
                ->sortable(),

            Text::make('Bank Transaction ID', 'bank_transaction_id')
                ->onlyOnDetail(),

            Text::make('Bank Description')
                ->sortable(),

            Text::make('Note')
                ->sortable(),

            Text::make('Kind')
                ->onlyOnDetail(),

            Text::make('Transaction Reference')
                ->sortable(),

            Text::make('Status')
                ->sortable(),

            DateTime::make('Transaction Created At')
                ->sortable(),

            DateTime::make('Transaction Posted At')
                ->sortable(),

            Currency::make('Net Amount')
                ->sortable(),

            Number::make('Check Number')
                ->sortable(),

            URL::make(
                'View in Mercury',
                fn (): ?string => $this->bank === 'mercury'
                    ? 'https://mercury.com/transactions/'.$this->bank_transaction_id
                    : null
            )
                ->onlyOnDetail(),

            HasOne::make('Expense Payment', 'expensePayment', ExpensePayment::class),

            Panel::make('Timestamps', [
                DateTime::make('Created', 'created_at')
                    ->onlyOnDetail(),

                DateTime::make('Last Updated', 'updated_at')
                    ->onlyOnDetail(),
            ]),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<\Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<\Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<\Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            UploadBankStatement::make()
                ->canSee(static fn (NovaRequest $request): bool => $request->user()->can('update-bank-transactions'))
                ->canRun(
                    static fn (
                        NovaRequest $request,
                        \App\Models\ExpenseReport $expenseReport
                    ): bool => $request->user()->can('update-bank-transactions')
                ),
            RefreshMercuryTransactions::make()
                ->canSee(static fn (NovaRequest $request): bool => $request->user()->can('update-bank-transactions'))
                ->canRun(
                    static fn (
                        NovaRequest $request,
                        \App\Models\ExpenseReport $expenseReport
                    ): bool => $request->user()->can('update-bank-transactions')
                ),
            MatchBankTransaction::make()
                ->canSee(static fn (NovaRequest $request): bool => true)
                ->canRun(static fn (NovaRequest $request, \App\Models\BankTransaction $bankTransaction): bool => true),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): string
    {
        return ($this->transaction_created_at?->format('Y-m-d') ?? $this->transaction_posted_at?->format('Y-m-d'))
            .' | '.$this->bank_description
            .' | '.($this->note === null ? '' : $this->note.' | ')
            .'$'.number_format(abs($this->net_amount), 2);
    }
}
