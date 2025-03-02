<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore

namespace App\Nova\Actions;

use App\Models\EngagePurchaseRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

class MatchAttachment extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Find Engage Request';

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
     * @param  \Illuminate\Support\Collection<int,\App\Models\Attachment>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $attachment = $models->sole();

        \App\Jobs\MatchAttachment::dispatchSync($attachment);

        try {
            $purchase_request = EngagePurchaseRequest::whereExpenseReportId($attachment->attachable->expenseReport->id)
                ->sole();

            return ActionResponse::visit(route(
                'nova.pages.detail',
                [
                    'resource' => \App\Nova\EngagePurchaseRequest::uriKey(),
                    'resourceId' => $purchase_request->id,
                ],
                false
            ));
        } catch (ModelNotFoundException) {
            return ActionResponse::danger('Could not find matching Engage request.');
        } catch (MultipleRecordsFoundException) {
            return ActionResponse::message('Matched more than one Engage request.');
        }
    }
}
