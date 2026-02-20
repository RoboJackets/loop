<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpenseReportPolicy
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
    public function view(User $user, ExpenseReport $expenseReport): true
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }
}
