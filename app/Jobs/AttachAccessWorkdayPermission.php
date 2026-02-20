<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LdapRecord\Container;

class AttachAccessWorkdayPermission implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @psalm-mutation-free
     */
    public function __construct(private readonly User $user)
    {
        $this->queue = 'ldap';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->user->can('access-workday')) {
            return;
        }

        if (
            ! collect(Container::getDefaultConnection()
                ->query()
                ->where('uid', '=', $this->user->username)
                ->where('employeeType', '=', 'employee')
                ->get())->isEmpty()
        ) {
            $this->user->givePermissionTo('access-workday');
        }
    }

    /**
     * The unique ID of the job.
     *
     * @psalm-mutation-free
     */
    public function uniqueId(): string
    {
        return strval($this->user->id);
    }
}
