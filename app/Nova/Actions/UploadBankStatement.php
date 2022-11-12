<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition

namespace App\Nova\Actions;

use App\Models\BankTransaction;
use App\Util\Sentry;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;

class UploadBankStatement extends Action
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
    public $confirmButtonText = 'Upload';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'Provide a PDF statement from your bank. This may take a few minutes to process.';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection<int,\App\Models\BankTransaction>  $models
     * @return array<string,string>
     *
     * @phan-suppress PhanTypeMismatchArgumentProbablyReal
     */
    public function handle(ActionFields $fields, Collection $models): array
    {
        $file_hash = hash_file('sha512', $fields->bank_statement->getPathname());

        $sensible_output = Cache::rememberForever(
            'bank_statement_'.$file_hash,
            static fn (): array => Sentry::wrapWithChildSpan(
                'sensible.sync_extraction',
                static fn (): array => json_decode(
                    (new Client(
                        [
                            'headers' => [
                                'User-Agent' => 'RoboJackets Loop on '.config('app.url'),
                                'Authorization' => 'Bearer '.config('services.sensible.token'),
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/pdf',
                            ],
                            'allow_redirects' => false,
                        ]
                    ))->post(
                        config('services.sensible.bank_statements_url'),
                        [
                            'body' => $fields->bank_statement->get(),
                        ]
                    )->getBody()->getContents()
                )
            )
        );

        $bank = $sensible_output['configuration'];

        $statement_date = Carbon::parse($sensible_output['parsed_document']['statement_date']['value'])
            ->setTimezone(config('app.timezone'));

        $transactions = $sensible_output['parsed_document']['transactions']['columns'];

        $transaction_count = count($transactions[0]['values']);

        for ($i = 0; $i < $transaction_count; $i++) {
            if ($bank === 'wells_fargo') {
                $bank_description = $transactions[2]['values'][$i]['value'];

                if ($bank_description === '') {
                    continue;
                }

                $matches = [];

                if (
                    preg_match(
                        '/(?<transaction_reference>L\d{12}|\d{12,13}|S\d{15})/',
                        $bank_description,
                        $matches
                    ) !== 1
                ) {
                    if ($bank_description === 'Check') {
                        $matches['transaction_reference'] = $transactions[1]['values'][$i]['value'];
                    } elseif ($bank_description === 'International Purchase Transaction Fee') {
                        $matches['transaction_reference'] = $transactions[0]['values'][$i]['value'].
                            $transactions[4]['values'][$i]['value'];
                    } elseif (str_contains(strtolower($bank_description), 'paypal')) {
                        if (preg_match('/(?<transaction_reference>\d{6})/', $bank_description, $matches) !== 1) {
                            throw new Exception(
                                'Could not extract transaction reference from \''.$bank_description.'\''
                            );
                        }
                    } elseif (str_contains(strtolower($bank_description), 'bill pay')) {
                        $matches['transaction_reference'] = $bank_description;
                    } elseif (str_contains(strtolower($bank_description), 'atm check deposit')) {
                        if (preg_match('/(?<transaction_reference>\d{7})/', $bank_description, $matches) !== 1) {
                            throw new Exception(
                                'Could not extract transaction reference from \''.$bank_description.'\''
                            );
                        }
                    } else {
                        throw new Exception('Could not extract transaction reference from \''.$bank_description.'\'');
                    }
                }

                $transaction_reference = $matches['transaction_reference'];

                $transaction_date = explode('/', $transactions[0]['values'][$i]['value']);

                BankTransaction::updateOrCreate(
                    [
                        'transaction_reference' => $transaction_reference,
                    ],
                    [
                        'bank' => $bank,
                        'bank_description' => $bank_description,
                        'transaction_posted_at' => Carbon::create(
                            $statement_date->year,
                            $transaction_date[0],
                            $transaction_date[1],
                            0,
                            0,
                            0,
                            config('app.timezone')
                        ),
                        'net_amount' => $transactions[3]['values'][$i]['value'] ??
                            -$transactions[4]['values'][$i]['value'],
                    ]
                );
            }
        }

        return Action::message('Successfully uploaded statement!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<\Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            File::make('Bank Statement')
                ->required(),
        ];
    }
}
