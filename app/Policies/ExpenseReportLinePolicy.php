<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpenseReportLine;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpenseReportLinePolicy
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
    public function view(User $user, ExpenseReportLine $expenseReportLine): true
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
    public function update(User $user, ExpenseReportLine $expenseReportLine): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExpenseReportLine $expenseReportLine): false
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExpenseReportLine $expenseReportLine): false
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExpenseReportLine $expenseReportLine): false
    {
        return false;
    }
}
