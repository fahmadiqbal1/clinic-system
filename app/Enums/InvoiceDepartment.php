<?php

namespace App\Enums;

/**
 * Invoice department types.
 * 
 * Centralizes department string constants to prevent typos
 * and enable type-safe department handling throughout the codebase.
 */
enum InvoiceDepartment: string
{
    case Consultation = 'consultation';
    case Laboratory = 'lab';
    case Radiology = 'radiology';
    case Pharmacy = 'pharmacy';

    /**
     * Get a human-readable label for the department.
     */
    public function label(): string
    {
        return match ($this) {
            self::Consultation => 'Consultation',
            self::Laboratory => 'Laboratory',
            self::Radiology => 'Radiology',
            self::Pharmacy => 'Pharmacy',
        };
    }

    /**
     * Get all department values as an array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get departments that handle physical items (inventory-based).
     */
    public static function inventoryDepartments(): array
    {
        return [
            self::Laboratory->value,
            self::Radiology->value,
            self::Pharmacy->value,
        ];
    }
}
