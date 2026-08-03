<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attachment;
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
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

class SyncEngage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @psalm-mutation-free
     */
    public function __construct()
    {
        $this->queue = 'engage';
    }

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
     * The base URL for the page that renders additional questions, including attachments, for a purchase request.
     */
    private const string ADDITIONAL_QUESTIONS_URL_PREFIX =
        'https://gatech.campuslabs.com/engage/actionCenter/organization/robojackets/finance/'
        .'financeRequestViewAdditionalQuestions/';

    /**
     * The base URL to resolve attachment download links against.
     */
    private const string ENGAGE_BASE_URL = 'https://gatech.campuslabs.com';

    /**
     * Matches HTML-escaped attachment download links within the additional questions page.
     */
    private const string ATTACHMENT_URL_REGEX =
        '~/engage/actionCenter/organization/robojackets/Finance/FileUploadQuestion/getdocument'
        .'\?DocumentId=[0-9]+&amp;RespondentId=[0-9]+~';

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
                ->orWhereNotIn('status', ['Completed', 'Canceled', 'Denied']);
        })
            ->orderBy('engage_id')
            ->get();

        Log::info(
            $need_details->count().' purchase requests need details from Engage',
            ['engage_ids' => $need_details->pluck('engage_id')->all()]
        );

        foreach ($need_details as $purchase_request) {
            self::syncPurchaseRequestDetails($client, $purchase_request);

            if ($purchase_request->deleted_at === null) {
                self::syncPurchaseRequestAttachments($client, $purchase_request);
            }
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
     * Download any attachments for a purchase request that are not already stored locally.
     */
    private static function syncPurchaseRequestAttachments(
        Client $client,
        EngagePurchaseRequest $purchase_request
    ): void {
        $response = Sentry::wrapWithChildSpan(
            'engage.get_additional_questions',
            static fn (): ResponseInterface => $client->get(
                self::ADDITIONAL_QUESTIONS_URL_PREFIX.$purchase_request->engage_id
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'Unexpected HTTP '.$response->getStatusCode().' response from Engage additional questions page for '
                    .'purchase request '.$purchase_request->engage_id
            );
        }

        $matches = [];

        preg_match_all(self::ATTACHMENT_URL_REGEX, $response->getBody()->getContents(), $matches);

        foreach (array_unique($matches[0]) as $attachment_url) {
            self::syncAttachment($client, $purchase_request, html_entity_decode($attachment_url));
        }
    }

    /**
     * Download a single attachment from Engage and store it locally, unless it already exists in the
     * database and on disk.
     */
    private static function syncAttachment(
        Client $client,
        EngagePurchaseRequest $purchase_request,
        string $attachment_url
    ): void {
        $query_string = [];

        parse_str(strval(parse_url($attachment_url, PHP_URL_QUERY)), $query_string);

        $document_id = intval($query_string['DocumentId']);

        $attachment = Attachment::whereEngageDocumentId($document_id)->first();
        \assert($attachment instanceof \App\Models\Attachment || $attachment === null);

        if ($attachment !== null && Storage::disk('local')->exists($attachment->filename)) {
            return;
        }

        $response = Sentry::wrapWithChildSpan(
            'engage.download_attachment',
            static fn (): ResponseInterface => $client->get(self::ENGAGE_BASE_URL.$attachment_url)
        );

        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'Unexpected HTTP '.$response->getStatusCode().' response from Engage for attachment '.$document_id
            );
        }

        if ($attachment === null) {
            $content_disposition = HeaderUtils::combine(
                HeaderUtils::split($response->getHeaderLine('Content-Disposition'), ';=')
            );

            if (array_key_exists('filename*', $content_disposition)) {
                // RFC 5987 extended value, formatted as charset'language'percent-encoded-filename
                $extended_value = explode('\'', strval($content_disposition['filename*']), 3);

                $remote_filename = rawurldecode($extended_value[2] ?? $extended_value[0]);
            } elseif (array_key_exists('filename', $content_disposition)) {
                $remote_filename = strval($content_disposition['filename']);
            } else {
                throw new Exception('No filename in Content-Disposition header for attachment '.$document_id);
            }

            $attachment = Attachment::create([
                'attachable_type' => $purchase_request->getMorphClass(),
                'attachable_id' => $purchase_request->id,
                'filename' => 'engage/'.$document_id.'/'.basename($remote_filename),
                'engage_document_id' => $document_id,
            ]);

            $attachment->searchable();
        }

        Storage::disk('local')->put($attachment->filename, $response->getBody()->getContents());

        GenerateThumbnail::dispatch(Storage::disk('local')->path($attachment->filename));
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
