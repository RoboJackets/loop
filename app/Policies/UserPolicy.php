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
     *
     * @psalm-pure
     */
    public function viewAny(User $actor): true
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @psalm-pure
     */
    public function view(User $actor, User $target): true
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): bool
    {
        return $actor->can('create-users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->can('update-users');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @psalm-pure
     */
    public function delete(User $actor, User $target): false
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @psalm-pure
     */
    public function restore(User $actor, User $target): false
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @psalm-pure
     */
    public function forceDelete(User $actor, User $target): false
    {
        return false;
    }
}
