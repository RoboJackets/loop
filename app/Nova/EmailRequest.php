<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for email requests.
 *
 * @extends \App\Nova\Resource<\App\Models\EmailRequest>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class EmailRequest extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\EmailRequest>
     */
    public static $model = \App\Models\EmailRequest::class;

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
    public static $group = 'SOFO';

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()
                ->sortable(),

            BelongsTo::make('Fiscal Year')
                ->sortable()
                ->required()
                ->rules('required'),

            Text::make('Vendor', 'vendor_name')
                ->sortable()
                ->required()
                ->rules('required'),

            Currency::make('Amount', 'vendor_document_amount')
                ->sortable()
                ->required()
                ->rules('required'),

            Text::make('Reference Number', 'vendor_document_reference')
                ->sortable()
                ->required()
                ->creationRules('unique:email_requests,vendor_document_reference')
                ->updateRules('unique:email_requests,vendor_document_reference,{{resourceId}}')
                ->rules('required'),

            Date::make('Document Date', 'vendor_document_date')
                ->sortable()
                ->required()
                ->rules('required'),

            BelongsTo::make('Workday Expense Report', 'expenseReport', ExpenseReport::class)
                ->sortable()
                ->nullable()
                ->searchable()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            URL::make('QuickBooks Invoice', 'quickbooks_invoice_url')
                ->displayUsing(fn (): ?int => $this->quickbooks_invoice_document_number)
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks'))
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            Number::make('QuickBooks Invoice ID', 'quickbooks_invoice_id')
                ->onlyOnForms()
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks'))
                ->hideWhenCreating(),

            Number::make('QuickBooks Invoice Document Number', 'quickbooks_invoice_document_number')
                ->onlyOnForms()
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-quickbooks'))
                ->hideWhenCreating(),

            File::make('Vendor Document', 'vendor_document_filename')
                ->disk('local')
                ->thumbnail(fn (): ?string => $this->vendor_document_thumbnail_url)
                ->required()
                ->creationRules('required'),

            Panel::make('Sensible', [
                URL::make('View in Sensible', 'sensible_extraction_url')
                    ->onlyOnDetail()
                    ->canSee(static fn (Request $request): bool => $request->user()->can('access-sensible')),

                Text::make('Extraction ID', 'sensible_extraction_uuid')
                    ->onlyOnDetail()
                    ->canSee(static fn (Request $request): bool => $request->user()->can('access-sensible')),

                Code::make('Output', 'sensible_output')
                    ->json()
                    ->onlyOnDetail()
                    ->canSee(static fn (Request $request): bool => $request->user()->can('access-sensible')),
            ]),

            Panel::make('Timestamps', [
                DateTime::make('Email Sent', 'email_sent_at')
                    ->sortable()
                    ->required()
                    ->rules('required'),

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
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): ?string
    {
        if (
            $this->vendor_document_date === null ||
            $this->vendor_name === null ||
            $this->vendor_document_amount === null
        ) {
            return null;
        }

        return $this->vendor_document_date->format('Y-m-d')
            .' | '.$this->vendor_name
            .' | $'.number_format(abs($this->vendor_document_amount), 2);
    }
}