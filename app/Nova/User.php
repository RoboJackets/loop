<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Jeffbeltran\SanctumTokens\SanctumTokens;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Vyuldashev\NovaPermission\Permission;
use Vyuldashev\NovaPermission\Role;

/**
 * A Nova resource for users.
 *
 * @extends \App\Nova\Resource<\App\Models\User>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class User extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string
     */
    public static $model = \App\Models\User::class;

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
        'first_name',
        'last_name',
        'username',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Username')
                ->sortable()
                ->rules('required', 'max:127')
                ->creationRules('unique:users,username')
                ->updateRules('unique:users,username,{{resourceId}}'),

            Text::make('First Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Last Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'max:127')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Boolean::make('Active Employee')
                ->onlyOnDetail(),

            Number::make('Instance ID', 'workday_instance_id')
                ->onlyOnDetail(),

            URL::make('View in Workday', 'workday_url')
                ->canSee(static fn (Request $request): bool => $request->user()->can('access-workday'))
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            HasMany::make('External Committee Members', 'externalCommitteeMembers'),

            SanctumTokens::make()
                ->hideAbilities()
                ->canSee(static fn (Request $request): bool => $request->user()->can('update-user-tokens')),

            MorphToMany::make('Roles', 'roles', Role::class)
                ->canSee(static fn (Request $request): bool => $request->user()->can('update-user-permissions')),

            MorphToMany::make('Permissions', 'permissions', Permission::class)
                ->canSee(static fn (Request $request): bool => $request->user()->can('update-user-permissions')),

            new Panel(
                'Timestamps',
                [
                    DateTime::make('Created', 'created_at')
                        ->onlyOnDetail(),

                    DateTime::make('Last Updated', 'updated_at')
                        ->onlyOnDetail(),
                ]
            ),
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
        $role = $this->roles()->orderBy('id')->first();

        return $role === null ? null : ucfirst($role->name);
    }
}
