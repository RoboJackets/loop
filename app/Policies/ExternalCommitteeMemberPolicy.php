<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExternalCommitteeMember;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExternalCommitteeMemberPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @psalm-pure
     */
    public function viewAny(User $user): true
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @psalm-pure
     */
    public function view(User $user, ExternalCommitteeMember $externalCommitteeMember): true
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @psalm-pure
     */
    public function create(User $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExternalCommitteeMember $externalCommitteeMember): bool
    {
        return $user->can('access-workday');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @psalm-pure
     */
    public function delete(User $user, ExternalCommitteeMember $externalCommitteeMember): false
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @psalm-pure
     */
    public function restore(User $user, ExternalCommitteeMember $externalCommitteeMember): false
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @psalm-pure
     */
    public function forceDelete(User $user, ExternalCommitteeMember $externalCommitteeMember): false
    {
        return false;
    }
}
