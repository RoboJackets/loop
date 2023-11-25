<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Jobs\ProcessSensibleOutput as Job;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ProcessSensibleOutput extends Action
{
    /**
     * Indicates if this action is only available on the resource detail view.
     *
     * @var bool
     */
    public $onlyOnDetail = true;

    /**
     * Determine where the action redirection should be without confirmation.
     *
     * @var bool
     */
    public $withoutConfirmation = true;

    /**
     * Disables action log events for this action.
     *
     * @var bool
     */
    public $withoutActionEvents = true;

    /**
     * Determine if the filter or action should be available for the given request.
     */
    public function authorizedToSee(Request $request): bool
    {
        return $request->user()->can('access-sensible');
    }

    /**
     * Determine if the action is executable for the given request.
     */
    public function authorizedToRun(Request $request, $model): bool
    {
        return $request->user()->can('access-sensible');
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\EmailRequest>  $models
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        Job::dispatchSync($models->sole());

        return Action::message('Successfully processed Sensible output!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<\Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
