<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Models\ExpensePayment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class MatchBankTransaction extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Find Expense Payment';

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
     * @param  \Illuminate\Support\Collection<int,\App\Models\BankTransaction>  $models
     */
    public function handle(ActionFields $fields, Collection $models): array
    {
        $bank_transaction = $models->sole();

        \App\Jobs\MatchBankTransaction::dispatchSync($bank_transaction);

        try {
            $payment = ExpensePayment::whereBankTransactionId($bank_transaction->id)->sole();

            return Action::visit(route(
                'nova.pages.detail',
                [
                    'resource' => \App\Nova\ExpensePayment::uriKey(),
                    'resourceId' => $payment->id,
                ],
                false
            ));
        } catch (ModelNotFoundException) {
            return Action::danger('Could not find matching expense payment.');
        } catch (MultipleRecordsFoundException) {
            return Action::message('Matched more than one expense payment.');
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
