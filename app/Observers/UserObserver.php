<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\AttachAccessWorkdayPermission;
use App\Models\User;

class UserObserver
{
    public function saved(User $user): void
    {
        AttachAccessWorkdayPermission::dispatch($user);
    }
}
