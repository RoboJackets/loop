<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): true
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmailRequest $emailRequest): true
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmailRequest $emailRequest): bool
    {
        return $user->can('update-email-requests');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmailRequest $emailRequest): bool
    {
        return $user->can('delete-email-requests');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmailRequest $emailRequest): bool
    {
        return $user->can('delete-email-requests');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmailRequest $emailRequest): false
    {
        return false;
    }

    public function replicate(User $user, EmailRequest $emailRequest): false
    {
        return false;
    }
}
