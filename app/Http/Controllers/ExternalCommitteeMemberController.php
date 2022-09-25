<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpsertExternalCommitteeMember;
use App\Models\ExternalCommitteeMember;
use Illuminate\Http\JsonResponse;

class ExternalCommitteeMemberController extends Controller
{
    private const WORKDAY_NAME_REGEX = '/^(?P<name>^[a-zA-Z\s]+)\s+\(ECM\)(?P<inactive>\s+-\s+Inactive)?$/';

    /**
     * Handle the incoming request.
     *
     * @phan-suppress PhanTypeMismatchDimFetch
     */
    public function __invoke(UpsertExternalCommitteeMember $request): JsonResponse
    {
        $workday_instance_id = explode('$', $request['title']['instances'][0]['instanceId'])[1];
        $workday_external_committee_member_id = $request['body']['children'][0]['value'];
        $title_text = $request['title']['instances'][0]['text'];

        $matches = [];
        if (preg_match(self::WORKDAY_NAME_REGEX, $title_text, $matches) === 1) {
            $name = $matches['name'];
            $active = ! array_key_exists('inactive', $matches);
        } else {
            return response(status: 400)->json([
                'error' => 'failed to parse name',
            ]);
        }

        $model = ExternalCommitteeMember::updateOrCreate([
            'workday_instance_id' => intval($workday_instance_id),
            'workday_external_committee_member_id' => $workday_external_committee_member_id,
        ], [
            'name' => $name,
            'active' => $active,
        ]);

        return response()->json($model);
    }
}
