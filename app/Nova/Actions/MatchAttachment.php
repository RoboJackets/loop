<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore

namespace App\Nova\Actions;

use App\Exceptions\CouldNotExtractEngagePurchaseRequestNumber;
use App\Models\EngagePurchaseRequest;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

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
     *
     * @phan-suppress PhanTypeMismatchArgumentNullable
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $attachment = $models->sole();

        if (
            str_ends_with(strtolower($attachment->filename), '.pdf') &&
            Storage::disk('local')->exists($attachment->filename)
        ) {
            try {
                $purchase_request_number = EngagePurchaseRequest::getPurchaseRequestNumberFromText(
                    $attachment->toSearchableArray()['full_text']
                );
            } catch (CouldNotExtractEngagePurchaseRequestNumber|FileNotFoundException) {
                return ActionResponse::danger('Unable to extract an Engage request number from this attachment.');
            }
        } else {
            return ActionResponse::danger('Attachment isn\'t parseable.');
        }

        try {
            $purchase_request = EngagePurchaseRequest::whereEngageRequestNumber($purchase_request_number)
                ->whereDoesntHave('expenseReport')
                ->where('status', '=', 'Approved')
                ->sole();

            $purchase_request->expense_report_id = $attachment->attachable->expenseReport->id;
            $purchase_request->save();

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
