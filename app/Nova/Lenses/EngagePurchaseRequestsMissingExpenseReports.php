<?php

declare(strict_types=1);

namespace App\Nova\Lenses;

use App\Models\EngagePurchaseRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

class EngagePurchaseRequestsMissingExpenseReports extends Lens
{
    /**
     * The displayable name of the lens.
     *
     * @var string
     */
    public $name = 'Engage Requests Missing Expense Reports';

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EngagePurchaseRequest>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\EngagePurchaseRequest>
     */
    public static function query(LensRequest $request, $query): Builder
    {
        return $request->withOrdering($request->withFilters(
            $query->whereDoesntHave('expenseReport')
                ->where(static function (Builder $query): void {
                    $query->where('payee_first_name', 'like', '%robojackets%')
                        ->orWhere('payee_last_name', 'like', '%robojackets%');
                })
        ));
    }

    /**
     * Get the fields available to the lens.
     *
     * @return array<int,\Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('Request Number', 'engage_request_number')
                ->sortable(),

            Badge::make('Step', 'current_step_name')
                ->map([
                    'Submitted' => 'info',
                    'Send to SOFO Accountant' => 'info',
                    'Sent back for edits' => 'danger',
                    'Check Request Sent' => 'success',
                ])
                ->sortable(),

            Text::make('Subject')
                ->sortable(),

            DateTime::make('Submitted', 'submitted_at')
                ->sortable(),

            Currency::make('Submitted Amount')
                ->sortable(),

            Currency::make('Approved Amount')
                ->sortable(),

            Text::make('Payee First Name', 'payee_first_name')
                ->sortable(),

            Text::make('Payee Last Name', 'payee_last_name')
                ->sortable(),

            URL::make('QuickBooks Invoice', 'quickbooks_invoice_url')
                ->displayUsing(
                    static fn (
                        $value,
                        EngagePurchaseRequest $resource,
                        string $attribute
                    ): ?int => $resource->quickbooks_invoice_document_number
                )
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks')),
        ];
    }

    /**
     * Get the cards available on the lens.
     *
     * @return array<\Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @return array<\Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the URI key for the lens.
     */
    public function uriKey(): string
    {
        return 'missing-expense-reports';
    }
}
