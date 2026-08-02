<?php

declare(strict_types=1);

namespace App\Jobs;

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
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncEngage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The Engage API endpoint that lists purchase requests.
     */
    private const string PURCHASE_REQUEST_LIST_URL =
        'https://gatech.campuslabs.com/engage/api/finance/robojackets/requests/purchase/list-items';

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

        $need_details = [];

        foreach ($list_items as $item) {
            $submitted_at = $item['submittedOn'] === null ? null : Carbon::parse(strval($item['submittedOn']));

            $purchase_request = EngagePurchaseRequest::updateOrCreate(
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

            if (in_array($purchase_request->engage_id, $need_details, true)) {
                continue;
            }

            if ($purchase_request->submitted_by_user_id === null) {
                $need_details[] = $purchase_request->engage_id;
            } elseif (
                $purchase_request->status === 'Approved' && (
                    $purchase_request->approved_by_user_id === null || $purchase_request->approved_at === null
                )
            ) {
                $need_details[] = $purchase_request->engage_id;
            }
        }

        sort($need_details);

        Log::info(
            count($need_details).' purchase requests need details from Engage',
            ['engage_ids' => $need_details]
        );
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
