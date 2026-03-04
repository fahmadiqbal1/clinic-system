<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if user can view other user.
     */
    public function view(User $user, User $targetUser): bool
    {
        // Owner can view all users
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Users can view themselves
        return $user->id === $targetUser->id;
    }

    /**
     * Determine if user can update other user.
     */
    public function update(User $user, User $targetUser): bool
    {
        // Only Owner can update users
        return $user->hasRole('Owner');
    }

    /**
     * Determine if user can update own profile.
     */
    public function updateOwnProfile(User $user, User $targetUser): bool
    {
        // Users can update their own profile
        return $user->id === $targetUser->id;
    }

    /**
     * Determine if user can delete user (not themselves).
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Only Owner can delete users
        if (!$user->hasRole('Owner')) {
            return false;
        }

        // Cannot delete the last Owner
        if ($targetUser->hasRole('Owner')) {
            $ownerCount = User::role('Owner')->count();
            if ($ownerCount <= 1) {
                return false;
            }
        }

        // Cannot delete self
        return $user->id !== $targetUser->id;
    }

    /**
     * Determine if user can change role of another user.
     */
    public function changeRole(User $user, User $targetUser): bool
    {
        // Only Owner can change roles
        if (!$user->hasRole('Owner')) {
            return false;
        }

        // Owner cannot be demoted by non-owner (would be checked at controller level)
        // This policy checks authorization only
        return true;
    }

    /**
     * Determine if user can activate/deactivate users.
     */
    public function toggleActive(User $user, User $targetUser): bool
    {
        // Only Owner can toggle activation
        if (!$user->hasRole('Owner')) {
            return false;
        }

        // Cannot deactivate self
        if ($user->id === $targetUser->id) {
            return false;
        }

        return true;
    }
}
