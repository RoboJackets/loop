<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition
// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed

namespace App\Nova\Actions;

use App\Models\BankTransaction;
use App\Util\Sentry;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class RefreshMercuryTransactions extends Action
{
    /**
     * Indicates if this action is only available on the resource index view.
     *
     * @var bool
     */
    public $onlyOnIndex = true;

    /**
     * Indicates if the action can be run without any models.
     *
     * @var bool
     */
    public $standalone = true;

    /**
     * The text to be used for the action's confirm button.
     *
     * @var string
     */
    public $confirmButtonText = 'Refresh';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'This action will refresh transactions from Mercury, and will take a few seconds to process.';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection<int,\App\Models\BankTransaction>  $models
     * @return array<string,string>
     */
    public function handle(ActionFields $fields, Collection $models): array
    {
        $json = Sentry::wrapWithChildSpan(
            'mercury.retrieve_transactions',
            static fn (): array => json_decode(
                (new Client(
                    [
                        'headers' => [
                            'User-Agent' => 'RoboJackets Loop on '.config('app.url'),
                            'Authorization' => 'Bearer '.config('services.mercury.token'),
                            'Accept' => 'application/json',
                        ],
                        'allow_redirects' => false,
                    ]
                ))->get(
                    config('services.mercury.transactions_url'),
                    [
                        'query' => [
                            'start' => '2020-01-01T00:00:00.00Z',
                            'limit' => 1000,
                            'offset' => 0,
                        ],
                    ]
                )->getBody()->getContents(),
                true
            )
        );

        collect($json['transactions'])
            ->sortBy([
                ['createdAt', 'asc'],
                ['postedAt', 'asc'],
            ])->each(static function (array $transaction, int $key): void {
                $bank_description = $transaction['bankDescription'];

                $matches = [];

                if (
                    preg_match(
                        '/(?<transaction_reference>L\d{12}|M[a-zA-Z0-9]{10})/',
                        $bank_description ?? '',
                        $matches
                    ) === 1
                ) {
                    $transaction_reference = $matches['transaction_reference'];
                } else {
                    $transaction_reference = null;
                }

                $matches = [];

                if (preg_match('/Check #?(?<check_number>\d{6,7})/', $transaction['note'] ?? '', $matches) === 1) {
                    $check_number = $matches['check_number'];
                } else {
                    $check_number = null;
                }

                BankTransaction::updateOrCreate(
                    [
                        'bank' => 'mercury',
                        'bank_transaction_id' => $transaction['id'],
                    ],
                    [
                        'bank_description' => $bank_description ?? $transaction['counterpartyName'],
                        'note' => $transaction['note'],
                        'transaction_reference' => $transaction_reference,
                        'status' => $transaction['status'],
                        'transaction_created_at' => $transaction['createdAt'],
                        'transaction_posted_at' => $transaction['postedAt'],
                        'net_amount' => $transaction['amount'],
                        'check_number' => $check_number,
                        'kind' => $transaction['kind'],
                    ]
                );
            });

        return Action::message('All transactions refreshed!');
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
