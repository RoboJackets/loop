<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpsertWorker;
use App\Models\User;
use App\Util\Sentry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use LdapRecord\Container;

class WorkerController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @phan-suppress PhanTypeMismatchDimFetch
     * @phan-suppress PhanPluginNonBoolBranch
     */
    public function __invoke(UpsertWorker $request): JsonResponse
    {
        $workday_instance_id = explode('$', $request['title']['instances'][0]['instanceId'])[1];
        $first_name = $request['body']['compositeViewHeader']['contactInfo']['firstName'];
        $last_name = $request['body']['compositeViewHeader']['contactInfo']['lastName'];
        $email = $request['body']['compositeViewHeader']['contactInfo']['primaryEmail'];
        $active_employee = ! str_ends_with($request['title']['instances'][0]['text'], ' (Terminated)');

        $attributes = [
            'workday_instance_id' => $workday_instance_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'active_employee' => $active_employee,
        ];

        if (User::whereWorkdayInstanceId($workday_instance_id)->exists()) {
            $user = User::whereWorkdayInstanceId($workday_instance_id)->sole();
        } elseif (User::whereEmail($email)->exists()) {
            $user = User::whereEmail($email)->sole();
        } else {
            $username_for_email = Cache::rememberForever(
                'uid_'.$email,
                static function () use ($email): ?string {
                    $result = Sentry::wrapWithChildSpan(
                        'ldap.get_username_by_email',
                        static fn (): array => Container::getDefaultConnection()
                            ->query()
                            ->where('mail', '=', $email)
                            ->select('primaryUid')
                            ->get()
                    );

                    return $result === [] ? null : $result[0]['primaryuid'][0];
                }
            );

            if ($username_for_email === null) {
                return response()->json(
                    [
                        'error' => 'Could not determine username for worker',
                    ],
                    422
                );
            } elseif (User::whereUsername($username_for_email)->exists()) {
                $user = User::whereUsername($username_for_email)->sole();
            } else {
                $user = new User();
                $user->username = $username_for_email;
            }
        }

        $user->fill($attributes);
        $user->save();

        if ($user->active_employee) {
            $user->givePermissionTo('access-workday');
        } else {
            $user->revokePermissionTo('access-workday');
        }

        return response()->json($user);
    }
}
