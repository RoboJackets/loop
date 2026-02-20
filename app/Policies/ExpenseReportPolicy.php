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
    public function view(User $user, ExpenseReport $expenseReport): true
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
     *
     * @psalm-pure
     */
    public function update(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @psalm-pure
     */
    public function delete(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @psalm-pure
     */
    public function restore(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @psalm-pure
     */
    public function forceDelete(User $user, ExpenseReport $expenseReport): false
    {
        return false;
    }
}
