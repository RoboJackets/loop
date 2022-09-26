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
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExternalCommitteeMember $externalCommitteeMember): bool
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
    public function update(User $user, ExternalCommitteeMember $externalCommitteeMember): bool
    {
        return $user->can('access-workday');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExternalCommitteeMember $externalCommitteeMember): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExternalCommitteeMember $externalCommitteeMember): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExternalCommitteeMember $externalCommitteeMember): bool
    {
        return false;
    }
}
