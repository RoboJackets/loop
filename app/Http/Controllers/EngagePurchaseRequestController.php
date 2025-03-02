<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEngagePurchaseRequest;
use App\Http\Requests\UpsertEngagePurchaseRequests;
use App\Models\EngagePurchaseRequest;
use App\Models\FiscalYear;
use App\Models\User;
use App\Util\Sentry;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use LdapRecord\Container;

class EngagePurchaseRequestController
{
    /**
     * Accept Engage API output and create requests based on the data.
     *
     * @phan-suppress PhanTypeMismatchForeach
     */
    public function store(UpsertEngagePurchaseRequests $request): JsonResponse
    {
        $sync_purchase_requests = [];

        foreach ($request['items'] as $item) {
            $purchase_request = EngagePurchaseRequest::updateOrCreate(
                [
                    'engage_id' => $item['id'],
                    'engage_request_number' => $item['requestNumber'],
                ],
                [
                    'subject' => $item['name'],
                    'status' => $item['status'],
                    'current_step_name' => self::cleanFinanceStageName($item['currentStepName']),
                    'submitted_amount' => $item['submittedAmount'],
                    'submitted_at' => $item['submittedOn'] === null ? null : Carbon::parse($item['submittedOn']),
                    'approved_amount' => $item['approvedAmount'],
                    'deleted_at' => $item['deletedOn'] === null ? null : Carbon::parse($item['deletedOn']),
                    'fiscal_year_id' => $item['submittedOn'] === null ? null : FiscalYear::fromDate(
                        Carbon::parse($item['submittedOn'])
                    )->id,
                ]
            );

            if (! in_array($purchase_request['id'], $sync_purchase_requests, true)) {
                if ($purchase_request->submitted_by_user_id === null) {
                    $sync_purchase_requests[] = $purchase_request['engage_id'];
                } elseif (
                    $purchase_request->status === 'Approved' && (
                        $purchase_request->approved_by_user_id === null || $purchase_request->approved_at === null
                    )
                ) {
                    $sync_purchase_requests[] = $purchase_request['engage_id'];
                }
            }
        }

        sort($sync_purchase_requests);

        return response()->json([
            'requests' => $sync_purchase_requests,
        ]);
    }

    /**
     * Accept Engage API output and update the internal representation.
     *
     * @phan-suppress PhanTypeMismatchDimFetch
     */
    public function update(EngagePurchaseRequest $purchaseRequest, UpdateEngagePurchaseRequest $request): JsonResponse
    {
        $purchaseRequest->fill([
            'engage_id' => $request['id'],
            'engage_request_number' => $request['requestNumber'],
            'subject' => $request['subject'],
            'description' => $request['description'],
            'status' => $request['status'],
            'current_step_name' => self::cleanFinanceStageName($request['financeStage']['name']),
            'submitted_amount' => $request['submitted']['amount'],
            'submitted_at' => $request['submitted']['date'] === null ? null : Carbon::parse(
                $request['submitted']['date']
            ),
            'submitted_by_user_id' => self::getUserByEmailAddress($request['submitted']['email'])->id,
            'approved_amount' => $request['approved'] === null ? null : $request['approved']['amount'],
            'approved_at' => $request['approved'] === null ? null : (
                $request['approved']['date'] === null ? null : Carbon::parse($request['approved']['date'])
            ),
            'approved_by_user_id' => $request['approved'] === null ? null : self::getUserByEmailAddress(
                $request['approved']['email']
            )->id,
            'payee_first_name' => $request['payee']['firstName'],
            'payee_last_name' => $request['payee']['lastName'],
            'payee_address_line_one' => $request['payee']['street'],
            'payee_address_line_two' => $request['payee']['street2'],
            'payee_city' => $request['payee']['city'],
            'payee_state' => $request['payee']['state'],
            'payee_zip_code' => $request['payee']['zipCode'],
            'fiscal_year_id' => $request['submitted']['date'] === null ? null : FiscalYear::fromDate(
                Carbon::parse($request['submitted']['date'])
            )->id,
            'deleted_at' => $request['deletedOn'] === null ? null : Carbon::parse($request['deletedOn']),
        ]);
        $purchaseRequest->save();

        return response()->json($purchaseRequest);
    }

    private static function cleanFinanceStageName(string $input): string
    {
        if (str_contains($input, ':')) {
            $parts = explode(':', $input);

            return trim($parts[1]);
        }

        return $input;
    }

    /**
     * Return a User given an email address (actually a User Principal Name).
     */
    private static function getUserByEmailAddress(string $email): User
    {
        $parts = explode('@', $email);

        if (User::whereUsername($parts[0])->exists()) {
            $user = User::whereUsername($parts[0])->sole();

            $user->givePermissionTo('access-engage');

            return $user;
        }

        $result = Sentry::wrapWithChildSpan(
            'ldap.get_user_by_username',
            static fn (): array => Container::getDefaultConnection()
                ->query()
                ->where('uid', '=', $parts[0])
                ->select('sn', 'givenName', 'primaryUid', 'mail')
                ->get()
        );

        if (count($result) === 0) {
            throw new Exception('User '.$parts[0].' not in Whitepages');
        }

        $user = User::create([
            'first_name' => $result[0]['givenname'][0],
            'last_name' => $result[0]['sn'][0],
            'username' => $result[0]['primaryuid'][0],
            'email' => $email,
        ]);

        $user->givePermissionTo('access-engage');

        return $user;
    }
}
