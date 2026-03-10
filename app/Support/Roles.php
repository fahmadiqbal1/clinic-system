<?php

namespace App\Support;

/**
 * Role constants and helpers.
 * 
 * Centralizes role string constants to prevent typos and provide
 * convenient grouped role checks throughout the codebase.
 */
class Roles
{
    // Individual role constants
    public const OWNER = 'Owner';
    public const DOCTOR = 'Doctor';
    public const RECEPTIONIST = 'Receptionist';
    public const TRIAGE = 'Triage';
    public const LABORATORY = 'Laboratory';
    public const RADIOLOGY = 'Radiology';
    public const PHARMACY = 'Pharmacy';
    public const PATIENT = 'Patient';

    /**
     * All staff roles (excludes Patient).
     */
    public const ALL_STAFF = [
        self::OWNER,
        self::DOCTOR,
        self::RECEPTIONIST,
        self::TRIAGE,
        self::LABORATORY,
        self::RADIOLOGY,
        self::PHARMACY,
    ];

    /**
     * Clinical staff (direct patient care).
     */
    public const CLINICAL_STAFF = [
        self::DOCTOR,
        self::TRIAGE,
        self::LABORATORY,
        self::RADIOLOGY,
        self::PHARMACY,
    ];

    /**
     * Department staff who handle inventory.
     */
    public const INVENTORY_STAFF = [
        self::PHARMACY,
        self::LABORATORY,
        self::RADIOLOGY,
    ];

    /**
     * Staff who can receive payouts/commissions.
     */
    public const COMMISSION_ELIGIBLE = [
        self::DOCTOR,
        self::LABORATORY,
        self::RADIOLOGY,
        self::PHARMACY,
    ];

    /**
     * Administrative roles with broader access.
     */
    public const ADMIN_STAFF = [
        self::OWNER,
        self::RECEPTIONIST,
    ];

    /**
     * Get a pipe-separated string for middleware.
     * 
     * @param array $roles Array of role constants
     * @return string e.g., "Owner|Doctor|Receptionist"
     */
    public static function middleware(array $roles): string
    {
        return implode('|', $roles);
    }

    /**
     * Get all staff roles as pipe-separated string for middleware.
     */
    public static function allStaffMiddleware(): string
    {
        return self::middleware(self::ALL_STAFF);
    }

    /**
     * Get all roles (including Patient).
     */
    public static function all(): array
    {
        return array_merge(self::ALL_STAFF, [self::PATIENT]);
    }
}
