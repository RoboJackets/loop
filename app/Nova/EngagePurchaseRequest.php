<?php

declare(strict_types=1);

namespace App\Nova;

use App\Nova\Actions\SyncEngagePurchaseRequestToQuickBooks;
use App\Nova\Lenses\EngagePurchaseRequestsMissingExpenseReports;
use App\Nova\Lenses\EngagePurchaseRequestsMissingInvoices;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for Engage purchase requests.
 *
 * @extends \App\Nova\Resource<\App\Models\EngagePurchaseRequest>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class EngagePurchaseRequest extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\EngagePurchaseRequest>
     */
    public static $model = \App\Models\EngagePurchaseRequest::class;

    /**
     * Get the displayble label of the resource.
     */
    public static function label(): string
    {
        return 'Engage Requests';
    }

    /**
     * Get the displayble singular label of the resource.
     */
    public static function singularLabel(): string
    {
        return 'Engage Request';
    }

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'engage_request_number';

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
    public static $group = 'SOFO';

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('Request Number', 'engage_request_number')
                ->sortable(),

            BelongsTo::make('Fiscal Year')
                ->sortable(),

            Badge::make('Step', 'current_step_name')
                ->resolveUsing([\App\Models\EngagePurchaseRequest::class, 'fixStepSpelling'])
                ->map(\App\Models\EngagePurchaseRequest::STEP_NAME_BADGE_MAP)
                ->sortable(),

            Badge::make('Status', 'status')
                ->map([
                    'Unapproved' => 'info',
                    'Denied' => 'danger',
                    'Canceled' => 'danger',
                    'Approved' => 'success',
                ])
                ->sortable(),

            Text::make('Subject'),

            Text::make('Description')
                ->onlyOnDetail(),

            Currency::make('Submitted Amount')
                ->sortable(),

            BelongsTo::make('Submitted By', 'submittedBy', User::class),

            BelongsTo::make('Workday Expense Report', 'expenseReport', ExpenseReport::class)
                ->sortable()
                ->nullable()
                ->searchable(),

            URL::make('QuickBooks Invoice', 'quickbooks_invoice_url')
                ->displayUsing(fn (): ?int => $this->quickbooks_invoice_document_number)
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks'))
                ->hideWhenUpdating(),

            Number::make('QuickBooks Invoice ID', 'quickbooks_invoice_id')
                ->onlyOnForms()
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks')),

            Number::make('QuickBooks Invoice Document Number', 'quickbooks_invoice_document_number')
                ->onlyOnForms()
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks')),

            URL::make('View in Engage', 'engage_url')
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-engage'))
                ->onlyOnDetail(),

            MorphMany::make('Attachments', 'attachments', Attachment::class),

            Panel::make('Payee', [
                BelongsTo::make('User', 'payToUser', User::class)
                    ->onlyOnDetail(),

                Text::make('First Name', 'payee_first_name')
                    ->onlyOnDetail(),

                Text::make('Last Name', 'payee_last_name')
                    ->onlyOnDetail(),

                Text::make('Address Line One', 'payee_address_line_one')
                    ->onlyOnDetail(),

                Text::make('Address Line Two', 'payee_address_line_two')
                    ->onlyOnDetail(),

                Text::make('City', 'payee_city')
                    ->onlyOnDetail(),

                Text::make('State', 'payee_state')
                    ->onlyOnDetail(),

                Text::make('ZIP Code', 'payee_zip_code')
                    ->onlyOnDetail(),
            ]),

            Panel::make('Approval', [
                Currency::make('Approved Amount')
                    ->onlyOnDetail(),

                BelongsTo::make('Approved By', 'approvedBy', User::class)
                    ->onlyOnDetail(),

                DateTime::make('Approved At')
                    ->onlyOnDetail(),
            ]),

            Panel::make('Timestamps', [
                DateTime::make('Submitted', 'submitted_at')
                    ->sortable(),

                DateTime::make('Created', 'created_at')
                    ->onlyOnDetail(),

                DateTime::make('Last Updated', 'updated_at')
                    ->onlyOnDetail(),

                DateTime::make('Deleted', 'deleted_at')
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
            EngagePurchaseRequestsMissingExpenseReports::make(),
            EngagePurchaseRequestsMissingInvoices::make(),
        ];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        $resourceId = $request->resourceId ?? $request->resources;
        $user = $request->user();

        if ($resourceId === null || $user === null || ! $user->can('access-quickbooks')) {
            return [];
        }

        $engageRequest = \App\Models\EngagePurchaseRequest::whereId($resourceId)->withTrashed()->sole();

        if (
            $engageRequest->deleted_at !== null ||
            $engageRequest->quickbooks_invoice_id !== null ||
            $engageRequest->quickbooks_invoice_document_number !== null ||
            (
                $engageRequest->expense_report_id === null &&
                (
                    $engageRequest->status !== 'Approved' ||
                    $engageRequest->current_step_name !== 'Check Request Sent'
                )
            )
        ) {
            return [];
        }

        return [
            SyncEngagePurchaseRequestToQuickBooks::make()
                ->canSee(static fn (NovaRequest $request): bool => $request->user()->can('access-quickbooks'))
                ->canRun(
                    static fn (NovaRequest $request, \App\Models\EngagePurchaseRequest $engage): bool => $request
                        ->user()
                        ->can('access-quickbooks')
                ),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): string
    {
        return $this->submitted_at->format('Y-m-d')
            .' | '.$this->current_step_name
            .' | '.$this->subject
            .' | $'.number_format(abs($this->submitted_amount), 2);
    }
}
