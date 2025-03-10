<?php

declare(strict_types=1);

namespace App\Nova\Lenses;

use App\Models\EngagePurchaseRequest;
use App\Nova\ExpenseReport;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

class EngagePurchaseRequestsMissingInvoices extends Lens
{
    /**
     * The displayable name of the lens.
     *
     * @var string
     */
    public $name = 'Engage Requests Missing Invoices';

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<EngagePurchaseRequest>  $query
     * @return \Illuminate\Database\Eloquent\Builder<EngagePurchaseRequest>
     */
    #[\Override]
    public static function query(LensRequest $request, $query): Builder
    {
        return $request->withOrdering($request->withFilters(
            $query->whereNull('quickbooks_invoice_id')
                ->where(static function (Builder $query): void {
                    $query->where('payee_first_name', 'like', '%robojackets%')
                        ->orWhere('payee_last_name', 'like', '%robojackets%');
                })
                ->whereNotLike('subject', 'pcard%')
                ->where('status', '=', 'Approved')
                ->where('current_step_name', '=', 'Check Request Sent')
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
            Number::make('Request Number', 'engage_request_number')
                ->sortable(),

            Badge::make('Step', 'current_step_name')
                ->resolveUsing([EngagePurchaseRequest::class, 'fixStepSpelling'])
                ->map(EngagePurchaseRequest::STEP_NAME_BADGE_MAP)
                ->sortable(),

            Badge::make('Status', 'status')
                ->map([
                    'Unapproved' => 'info',
                    'Denied' => 'danger',
                    'Canceled' => 'danger',
                    'Approved' => 'success',
                    'Completed' => 'success',
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

            BelongsTo::make('Workday Expense Report', 'expenseReport', ExpenseReport::class)
                ->sortable()
                ->nullable(),
        ];
    }

    /**
     * Get the URI key for the lens.
     */
    #[\Override]
    public function uriKey(): string
    {
        return 'missing-invoices';
    }
}
