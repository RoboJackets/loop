<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FundingAllocationLine;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FundingAllocationLinePolicy
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
    public function view(User $user, FundingAllocationLine $fundingAllocationLine): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create-funding-allocations');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FundingAllocationLine $fundingAllocationLine): bool
    {
        return $user->can('update-funding-allocations');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FundingAllocationLine $fundingAllocationLine): bool
    {
        return $user->can('delete-funding-allocations');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FundingAllocationLine $fundingAllocationLine): bool
    {
        return $user->can('delete-funding-allocations');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FundingAllocationLine $fundingAllocationLine): bool
    {
        return false;
    }
}
