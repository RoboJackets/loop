<?php

declare(strict_types=1);

namespace App\Nova\Lenses;

use App\Models\DocuSignEnvelope;
use App\Nova\ExpenseReport;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

class ReimbursementsMissingInvoices extends Lens
{
    /**
     * The displayable name of the lens.
     *
     * @var string
     */
    public $name = 'Reimbursements Missing Invoices';

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\DocuSignEnvelope>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\DocuSignEnvelope>
     */
    public static function query(LensRequest $request, $query): Builder
    {
        return $request->withOrdering($request->withFilters(
            $query->whereNull('quickbooks_invoice_id')
                ->whereDoesntHave('replacedBy')
                ->whereDoesntHave('duplicateOf')
                ->whereDoesntHave('payToUser')
                ->whereHas(
                    'fiscalYear',
                    static function (Builder $query): void {
                        $query->where('in_scope_for_quickbooks', '=', true);
                    }
                )
                ->whereIn('type', ['purchase_reimbursement', 'travel_reimbursement'])
                ->where('lost', '=', false)
                ->where('internal_cost_transfer', '=', false)
                ->where('submission_error', '=', false)
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
            ID::make()
                ->sortable(),

            DateTime::make('Submitted', 'submitted_at')
                ->sortable(),

            Select::make('Form Type', 'type')
                ->sortable()
                ->options(DocuSignEnvelope::$types)
                ->displayUsingLabels(),

            Text::make('Description')
                ->sortable(),

            Currency::make('Amount')
                ->sortable(),

            BelongsTo::make('Workday Expense Report', 'expenseReport', ExpenseReport::class)
                ->sortable()
                ->nullable()
                ->searchable(),
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
        return 'missing-invoices';
    }
}
