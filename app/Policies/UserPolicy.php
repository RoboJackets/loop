<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, User $target): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $actor, User $target): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, User $target): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, User $target): bool
    {
        return false;
    }
}
