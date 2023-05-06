<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Jobs\SubmitDocuSignEnvelopeToSensible;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class RunSensibleExtraction extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Run Sensible Extraction';

    /**
     * Indicates if this action is only available on the resource detail view.
     *
     * @var bool
     */
    public $onlyOnDetail = true;

    /**
     * The text to be used for the action's confirm button.
     *
     * @var string
     */
    public $confirmButtonText = 'Run Extraction';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'Are you sure you want to submit this document to Sensible?';

    /**
     * Disables action log events for this action.
     *
     * @var bool
     */
    public $withoutActionEvents = true;

    /**
     * The metadata for the element.
     *
     * @var array<string, bool>
     */
    public $meta = [
        'destructive' => true,
    ];

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
     * @param  \Illuminate\Support\Collection<int,\App\Models\DocuSignEnvelope>  $models
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $envelope = $models->sole();

        SubmitDocuSignEnvelopeToSensible::dispatchSync($envelope);

        $envelope->refresh();

        return self::openInNewTab($envelope->sensible_extraction_url);
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
