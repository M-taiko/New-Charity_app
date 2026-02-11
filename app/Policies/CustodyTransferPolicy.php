<?php

namespace App\Policies;

use App\Models\CustodyTransfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustodyTransferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustodyTransfer $custodyTransfer): bool
    {
        // Allow if user is the sender or receiver
        if ($user->id === $custodyTransfer->from_agent_id || $user->id === $custodyTransfer->to_agent_id) {
            return true;
        }

        // Allow if user is accountant or manager
        if ($user->hasAnyRole(['محاسب', 'مدير'])) {
            return true;
        }

        return false;
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
    public function update(User $user, CustodyTransfer $custodyTransfer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustodyTransfer $custodyTransfer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustodyTransfer $custodyTransfer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustodyTransfer $custodyTransfer): bool
    {
        return false;
    }
}
