<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    /**
     * Determine if user can view stock movement.
     */
    public function view(User $user, StockMovement $movement): bool
    {
        // Owner can view all movements
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Department staff can view movements for their department
        $item = $movement->inventoryItem;
        
        if ($user->hasRole('Laboratory') && $item->department === 'laboratory') {
            return true;
        }

        if ($user->hasRole('Radiology') && $item->department === 'radiology') {
            return true;
        }

        if ($user->hasRole('Pharmacy') && $item->department === 'pharmacy') {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete stock movement (audit trail - should not delete).
     */
    public function delete(User $user, StockMovement $movement): bool
    {
        // Stock movements should not be deleted (audit trail)
        return false;
    }
}
