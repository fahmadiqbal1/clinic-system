<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    /**
     * Determine if user can view inventory item.
     */
    public function view(User $user, InventoryItem $item): bool
    {
        // Owner can view all items
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Department staff can view items for their department
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
     * Determine if user can update inventory item.
     */
    public function update(User $user, InventoryItem $item): bool
    {
        // Only Owner can update inventory
        return $user->hasRole('Owner');
    }

    /**
     * Determine if user can delete inventory item.
     */
    public function delete(User $user, InventoryItem $item): bool
    {
        // Only Owner can delete inventory
        if (!$user->hasRole('Owner')) {
            return false;
        }

        // Cannot delete items with stock movements (soft delete only)
        return $item->stockMovements()->count() === 0;
    }
}
