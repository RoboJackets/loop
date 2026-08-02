<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\EngagePurchaseRequest;
use App\Models\FiscalYear;
use App\Util\Engage;
use App\Util\Sentry;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class SyncEngage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The base URL for the Engage purchase request API.
     */
    private const string PURCHASE_REQUEST_URL_PREFIX =
        'https://gatech.campuslabs.com/engage/api/finance/robojackets/requests/purchase/';

    /**
     * The Engage API endpoint that lists purchase requests.
     */
    private const string PURCHASE_REQUEST_LIST_URL = self::PURCHASE_REQUEST_URL_PREFIX.'list-items';

    /**
     * The number of purchase requests to retrieve per page.
     */
    private const int PAGE_SIZE = 100;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = Sentry::wrapWithChildSpan('engage.authenticate', static fn (): Client => Engage::client());

        $list_items = Sentry::wrapWithChildSpan(
            'engage.list_purchase_requests',
            static fn (): array => self::retrievePurchaseRequestListItems($client)
        );

        Log::info('Retrieved '.count($list_items).' purchase requests from Engage');

        foreach ($list_items as $item) {
            self::upsertPurchaseRequestListItem($item);
        }

        $need_details = EngagePurchaseRequest::where(static function (Builder $query): void {
            $query->whereNull('submitted_by_user_id')
                ->orWhere(static function (Builder $query): void {
                    $query->whereIn('status', ['Approved', 'Completed'])
                        ->where(static function (Builder $query): void {
                            $query->whereNull('approved_by_user_id')
                                ->orWhereNull('approved_at');
                        });
                })
                ->orWhereDoesntHave('attachments')
                ->orWhereNotIn('status', ['Completed', 'Canceled']);
        })
            ->orderBy('engage_id')
            ->get();

        Log::info(
            $need_details->count().' purchase requests need details from Engage',
            ['engage_ids' => $need_details->pluck('engage_id')->all()]
        );

        foreach ($need_details as $purchase_request) {
            self::syncPurchaseRequestDetails($client, $purchase_request);
        }

        DataSource::updateOrCreate(
            [
                'name' => 'engage',
            ],
            [
                'synced_at' => Carbon::now(),
            ]
        );

        Log::info('Engage sync complete');
    }

    /**
     * Upsert a purchase request list item into the database.
     *
     * @param  array<string,int|float|string|null>  $item
     */
    private static function upsertPurchaseRequestListItem(array $item): void
    {
        $submitted_at = $item['submittedOn'] === null ? null : Carbon::parse(strval($item['submittedOn']));

        EngagePurchaseRequest::updateOrCreate(
            [
                'engage_id' => $item['id'],
                'engage_request_number' => $item['requestNumber'],
            ],
            [
                'subject' => $item['name'],
                'status' => $item['status'],
                'current_step_name' => Engage::cleanFinanceStageName(strval($item['currentStepName'])),
                'submitted_amount' => $item['submittedAmount'],
                'submitted_at' => $submitted_at,
                'approved_amount' => $item['approvedAmount'],
                'deleted_at' => $item['deletedOn'] === null ? null : Carbon::parse(strval($item['deletedOn'])),
                'fiscal_year_id' => $submitted_at === null ? null : FiscalYear::firstOrCreate([
                    'ending_year' => FiscalYear::intFromDate($submitted_at),
                ])->id,
            ]
        );
    }

    /**
     * Fetch the details for a purchase request from Engage and update the local representation.
     */
    private static function syncPurchaseRequestDetails(Client $client, EngagePurchaseRequest $purchase_request): void
    {
        $response = Sentry::wrapWithChildSpan(
            'engage.get_purchase_request',
            static fn (): ResponseInterface => $client->get(
                self::PURCHASE_REQUEST_URL_PREFIX.$purchase_request->engage_id.'/',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'Unexpected HTTP '.$response->getStatusCode().' response from Engage for purchase request '
                    .$purchase_request->engage_id
            );
        }

        $detail = json_decode($response->getBody()->getContents(), true);

        $submitted_at = $detail['submitted']['date'] === null ? null : Carbon::parse(
            strval($detail['submitted']['date'])
        );

        $purchase_request->fill([
            'engage_id' => $detail['id'],
            'engage_request_number' => $detail['requestNumber'],
            'subject' => $detail['subject'],
            'description' => $detail['description'],
            'status' => $detail['status'],
            'current_step_name' => Engage::cleanFinanceStageName(strval($detail['financeStage']['name'])),
            'submitted_amount' => $detail['submitted']['amount'],
            'submitted_at' => $submitted_at,
            'submitted_by_user_id' => Engage::getUserByEmailAddress(strval($detail['submitted']['email']))->id,
            'approved_amount' => $detail['approved'] === null ? null : $detail['approved']['amount'],
            'approved_at' => $detail['approved'] === null ? null : (
                ($detail['approved']['date'] ?? null) === null ? null : Carbon::parse(
                    strval($detail['approved']['date'])
                )
            ),
            'approved_by_user_id' => $detail['approved'] === null ? null : Engage::getUserByEmailAddress(
                strval($detail['approved']['email'])
            )->id,
            'payee_first_name' => $detail['payee']['firstName'],
            'payee_last_name' => $detail['payee']['lastName'],
            'payee_address_line_one' => $detail['payee']['street'],
            'payee_address_line_two' => $detail['payee']['street2'],
            'payee_city' => $detail['payee']['city'],
            'payee_state' => $detail['payee']['state'],
            'payee_zip_code' => $detail['payee']['zipCode'],
            'fiscal_year_id' => $submitted_at === null ? null : FiscalYear::firstOrCreate([
                'ending_year' => FiscalYear::intFromDate($submitted_at),
            ])->id,
            'deleted_at' => $detail['deletedOn'] === null ? null : Carbon::parse(strval($detail['deletedOn'])),
        ]);
        $purchase_request->save();
    }

    /**
     * Retrieve all purchase request list items from Engage, paginating through the full list.
     *
     * @return array<int,array<string,int|float|string|null>>
     */
    private static function retrievePurchaseRequestListItems(Client $client): array
    {
        $list_items = [];
        $retrieved_items = 0;

        do {
            $response = $client->get(self::PURCHASE_REQUEST_LIST_URL, [
                'query' => [
                    'take' => self::PAGE_SIZE,
                    'skip' => $retrieved_items,
                    'status' => 'All',
                    'searchText' => '',
                    'categoryId' => 0,
                    'stageId' => 0,
                    'branchId' => 0,
                    'processId' => 0,
                    'orderByField' => 'SubmittedOn',
                    'orderByDirection' => 'Descending',
                    'showOnlyRecentlyDeleted' => 'false',
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception(
                    'Unexpected HTTP '.$response->getStatusCode().' response from Engage purchase request list'
                );
            }

            $page = json_decode($response->getBody()->getContents(), true);

            $total_items = intval($page['totalItems']);

            if ($page['items'] === []) {
                break;
            }

            $list_items = array_merge($list_items, $page['items']);
            $retrieved_items = count($list_items);
        } while ($retrieved_items < $total_items);

        return $list_items;
    }
}
