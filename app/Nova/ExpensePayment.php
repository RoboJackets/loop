<?php

declare(strict_types=1);

namespace App\Nova;

use App\Nova\Actions\SyncExpensePaymentToQuickBooks;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for Workday Expense Payments.
 *
 * @extends \App\Nova\Resource<\App\Models\ExpensePayment>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class ExpensePayment extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\ExpensePayment::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'transaction_reference';

    /**
     * The columns that should be searched.
     *
     * @var array<string>
     */
    public static $search = [
        'transaction_reference',
        'amount',
    ];

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Workday';

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Number::make('Check Number', 'transaction_reference')
                ->sortable()
                ->readonly(),

            Number::make('Instance ID', 'workday_instance_id')
                ->onlyOnDetail(),

            Badge::make('Status')
                ->map([
                    'Complete' => 'success',
                    'Canceled' => 'danger',
                ])
                ->sortable()
                ->hideWhenUpdating(),

            Select::make('Status')
                ->sortable()
                ->options([
                    'Complete' => 'Complete',
                    'Canceled' => 'Canceled',
                ])
                ->displayUsingLabels()
                ->onlyOnForms(),

            Boolean::make('Reconciled')
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

            URL::make('QuickBooks Payment', 'quickbooks_payment_url')
                ->displayUsing(fn (): ?int => $this->quickbooks_payment_id)
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks'))
                ->hideWhenUpdating(),

            Number::make('QuickBooks Payment ID', 'quickbooks_payment_id')
                ->onlyOnForms()
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks'))
                ->hideWhenUpdating(),

            HasMany::make('Expense Reports', 'expenseReports'),

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
     *
     * @phan-suppress PhanTypeMismatchArgumentProbablyReal
     */
    public function actions(NovaRequest $request): array
    {
        return [
            SyncExpensePaymentToQuickBooks::make()
                ->canSee(static fn (NovaRequest $request): bool => $request->user()->can('access-quickbooks'))
                ->canRun(
                    static fn (
                        NovaRequest $request,
                        \App\Models\ExpensePayment $payment
                    ): bool => $request->user()->can('access-quickbooks') &&
                        $request->user()->quickbooks_access_token !== null &&
                        $payment->quickbooks_payment_id === null &&
                        $payment->payTo->user === null
                ),
        ];
    }
}
