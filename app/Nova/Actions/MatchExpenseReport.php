<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed

namespace App\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class MatchExpenseReport extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Find DocuSign Envelope';

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
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\ExpenseReport>  $models
     */
    public function handle(ActionFields $fields, Collection $models): array
    {
        $envelope = \App\Jobs\MatchExpenseReport::dispatchSync($models->sole());

        if ($envelope === null) {
            return Action::danger('Could not find matching DocuSign envelope.');
        } else {
            return Action::visit(route(
                'nova.pages.detail',
                [
                    'resource' => \App\Nova\DocuSignEnvelope::uriKey(),
                    'resourceId' => $envelope->id,
                ],
                false
            ));
        }
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
