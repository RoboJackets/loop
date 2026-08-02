<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Util\Engage;
use App\Util\Sentry;
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

        $purchase_requests = Sentry::wrapWithChildSpan(
            'engage.list_purchase_requests',
            static fn (): array => self::retrievePurchaseRequestListItems($client)
        );

        Log::info('Retrieved '.count($purchase_requests).' purchase requests from Engage');

        // Temporary demonstration output, to be replaced with loading into the database
        foreach ($purchase_requests as $purchase_request) {
            echo sprintf(
                "%7d  #%-5d  %-9s  %-10s  %9.2f  %s\n",
                $purchase_request['id'],
                $purchase_request['requestNumber'],
                $purchase_request['status'],
                substr(strval($purchase_request['submittedOn']), 0, 10),
                floatval($purchase_request['submittedAmount'] ?? 0),
                $purchase_request['name'].' ('.$purchase_request['submittedByName'].')'
            );
        }
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
