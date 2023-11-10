<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocuSignEnvelope;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocuSignEnvelopePolicy
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
    public function view(User $user, DocuSignEnvelope $docuSignEnvelope): bool
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
    public function update(User $user, DocuSignEnvelope $docuSignEnvelope): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocuSignEnvelope $docuSignEnvelope): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocuSignEnvelope $docuSignEnvelope): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocuSignEnvelope $docuSignEnvelope): bool
    {
        return false;
    }
}
