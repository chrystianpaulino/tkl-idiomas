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
     * Admins (school_admin / admin) bypass per-ability checks for payments.
     * The super_admin bypass is handled globally by Gate::before in
     * AppServiceProvider, so this hook only needs to grant access to
     * school-level admins. Non-admins fall through to the individual
     * ability methods (which all return false).
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
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
