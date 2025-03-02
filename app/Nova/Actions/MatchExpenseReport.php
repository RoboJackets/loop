<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed

namespace App\Nova\Actions;

use App\Models\EngagePurchaseRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class MatchExpenseReport extends Action
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
     * @param  \Illuminate\Support\Collection<int,\App\Models\ExpenseReport>  $models
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $expense_report = $models->sole();

        \App\Jobs\MatchExpenseReport::dispatchSync($expense_report);

        try {
            $purchase_request = EngagePurchaseRequest::whereExpenseReportId($expense_report->id)->sole();

            return Action::visit(route(
                'nova.pages.detail',
                [
                    'resource' => \App\Nova\EngagePurchaseRequest::uriKey(),
                    'resourceId' => $purchase_request->id,
                ],
                false
            ));
        } catch (ModelNotFoundException) {
            return Action::danger('Could not find matching Engage request.');
        } catch (MultipleRecordsFoundException) {
            return Action::message('Matched more than one Engage request.');
        }
    }
}
