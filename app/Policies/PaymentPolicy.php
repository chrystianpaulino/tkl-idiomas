<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Authorization policy for payment records.
 *
 * Uses the before() hook to grant full access to admins for all abilities.
 * Non-admin users are denied all payment operations. This ensures financial
 * data is only accessible to administrators.
 *
 * Registered manually in AppServiceProvider::boot() via Gate::policy().
 */
class PaymentPolicy
{
    /**
     * Admins bypass all checks. For non-admins, return null to fall through
     * to the individual ability methods (which all return false).
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}
