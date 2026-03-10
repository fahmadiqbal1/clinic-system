<?php

namespace App\Enums;

/**
 * Discount workflow status values.
 */
enum DiscountStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No Discount',
            self::Pending => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get the CSS class for status badges.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::None => 'bg-gray-100 text-gray-800',
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Approved => 'bg-green-100 text-green-800',
            self::Rejected => 'bg-red-100 text-red-800',
        };
    }
}
