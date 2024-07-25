<?php

declare(strict_types=1);

namespace App\Nova;

use App\Nova\Actions\MatchExpenseReport;
use App\Nova\Lenses\ExpenseReportsWithNoEngagePurchaseRequests;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for Workday Expense Reports.
 *
 * @extends \App\Nova\Resource<\App\Models\ExpenseReport>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class ExpenseReport extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\ExpenseReport::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'workday_expense_report_id';

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
    public static $group = 'Workday';

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<string>
     */
    public static $with = [
        'payTo',
        'fiscalYear',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Number', 'workday_expense_report_id')
                ->sortable(),

            BelongsTo::make('Fiscal Year', 'fiscalYear', FiscalYear::class)
                ->sortable(),

            Number::make('Instance ID', 'workday_instance_id')
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-workday'))
                ->onlyOnDetail(),

            Badge::make('Status')
                ->map([
                    // @phan-suppress-next-line PhanTypeInvalidArrayKeyLiteral
                    null => 'danger',
                    'Draft' => 'info',
                    'In Progress' => 'info',
                    'Waiting on Gift Manager' => 'info',
                    'Waiting on Cost Center Manager' => 'info',
                    'Waiting on Expense Partner' => 'info',
                    'Approved' => 'success',
                    'Paid' => 'success',
                    'Canceled' => 'danger',
                    'Sent Back' => 'danger',
                ])
                ->sortable(),

            BelongsTo::make('Pay To', 'payTo', ExternalCommitteeMember::class)
                ->sortable(),

            Text::make('Memo')
                ->sortable(),

            Date::make('Created Date', 'created_date')
                ->onlyOnDetail(),

            Date::make('Approval Date', 'approval_date')
                ->onlyOnDetail(),

            BelongsTo::make('Created By', 'createdBy', User::class)
                ->onlyOnDetail(),

            BelongsTo::make('Expense Payment', 'expensePayment', ExpensePayment::class)
                ->onlyOnDetail(),

            Currency::make('Amount')
                ->sortable(),

            Currency::make(
                'Envelopes Total Amount',
                fn (): string|int => $this->envelopes()->sum('docusign_envelopes.amount')
            )
                ->onlyOnDetail(),

            URL::make('View in Workday', 'workday_url')
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-workday')),

            HasMany::make('Lines', 'lines', ExpenseReportLine::class),

            ...($this->engagePurchaseRequests()->count() === 0 ||
                $this->emailRequests()->count() === 0 ||
                $this->envelopes()->count() > 0 ? [
                    HasMany::make('DocuSign Envelopes', 'envelopes', DocuSignEnvelope::class),
                ] : []),

            ...($this->envelopes()->count() === 0 ||
                $this->emailRequests()->count() === 0 ||
                $this->engagePurchaseRequests()->count() > 0 ? [
                    HasMany::make('Engage Requests', 'engagePurchaseRequests', EngagePurchaseRequest::class),
                ] : []),

            ...($this->engagePurchaseRequests()->count() === 0 ||
                $this->envelopes()->count() === 0 ||
                $this->emailRequests()->count() > 0 ? [
                    HasMany::make('Email Requests'),
                ] : []),

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
        return [
            ExpenseReportsWithNoEngagePurchaseRequests::make(),
        ];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            MatchExpenseReport::make()
                ->canSee(static fn (NovaRequest $request): bool => true)
                ->canRun(static fn (NovaRequest $request, \App\Models\ExpenseReport $expenseReport): bool => true),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): string
    {
        return $this->created_date->format('Y-m-d')
            .' | '.$this->status
            .' | $'.number_format(abs($this->amount), 2);
    }
}
