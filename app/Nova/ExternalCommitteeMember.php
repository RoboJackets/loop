<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for Workday External Committee Members.
 *
 * @extends \App\Nova\Resource<\App\Models\ExternalCommitteeMember>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class ExternalCommitteeMember extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\ExternalCommitteeMember::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array<string>
     */
    public static $search = [
        'name',
        'workday_external_committee_member_id',
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
        'user',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    #[\Override]
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()
                ->sortable(),

            Number::make('Instance ID', 'workday_instance_id')
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-workday'))
                ->onlyOnDetail(),

            Text::make('Name')
                ->sortable()
                ->readonly(),

            Text::make('External Committee Member ID', 'workday_external_committee_member_id')
                ->sortable()
                ->readonly(),

            Boolean::make('Active')
                ->sortable()
                ->readonly(),

            BelongsTo::make('User')
                ->nullable()
                ->searchable(),

            URL::make('View in Workday', 'workday_url')
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-workday'))
                ->hideWhenUpdating(),

            HasMany::make('Expense Reports', 'expenseReports'),

            HasMany::make('Expense Payments', 'expensePayments'),

            Panel::make('Timestamps', [
                DateTime::make('Created', 'created_at')
                    ->onlyOnDetail(),

                DateTime::make('Last Updated', 'updated_at')
                    ->onlyOnDetail(),
            ]),
        ];
    }

    /**
     * Get the search result subtitle for the resource.
     */
    #[\Override]
    public function subtitle(): string
    {
        return $this->active ? 'Active' : 'Inactive';
    }
}
