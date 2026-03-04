<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolves a user's authorized department scope for read-only queries.
 * 
 * Single source of truth for department filtering logic.
 * Extracts role-to-department mapping from controllers.
 */
class DepartmentScope
{
    /**
     * Resolve department scope for authenticated user.
     * 
     * Returns null for Owner (can view all departments).
     * Returns specific department for staff with department-specific roles.
     * 
     * @param User $user
     * @return string|null Department slug or null for unrestricted (Owner)
     */
    public static function resolveForUser(User $user): ?string
    {
        // Owner role → view all departments
        if ($user->hasRole('Owner')) {
            return null;
        }

        // Department-specific roles
        if ($user->hasRole('Pharmacy')) {
            return 'pharmacy';
        }

        if ($user->hasRole('Laboratory')) {
            return 'laboratory';
        }

        if ($user->hasRole('Radiology')) {
            return 'radiology';
        }

        // Roles without department scope (Doctor, Receptionist, Triage, Patient)
        // cannot access intelligence dashboards anyway (via authorization)
        return null;
    }
}
