<?php

namespace App\Enums;

/**
 * Invoice status values.
 * 
 * Centralizes status constants for the invoice state machine.
 */
enum InvoiceStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the CSS class for status badges.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Paid => 'bg-indigo-100 text-indigo-800',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Check if this is a terminal state (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled]);
    }

    /**
     * Get allowed transitions from this status.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress, self::Paid, self::Cancelled],
            self::InProgress => [self::Completed, self::Paid, self::Cancelled],
            self::Completed => [self::Paid],
            self::Paid => [],
            self::Cancelled => [],
        };
    }
}
