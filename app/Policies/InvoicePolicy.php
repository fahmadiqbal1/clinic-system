<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine if user can view invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Owner can view all invoices
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Receptionist can view all invoices
        if ($user->hasRole('Receptionist')) {
            return true;
        }

        // Department staff can view invoices for their department
        if ($user->hasRole('Laboratory') && $invoice->department === 'lab') {
            return true;
        }

        if ($user->hasRole('Radiology') && $invoice->department === 'radiology') {
            return true;
        }

        if ($user->hasRole('Pharmacy') && $invoice->department === 'pharmacy') {
            return true;
        }

        // Doctor can view invoices for their patients
        if ($user->hasRole('Doctor') && $invoice->patient?->doctor_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can update invoice.
     *
     * Paid invoices are generally read-only, but lab/rad staff may update
     * work fields (report_text, performed_by_user_id) on paid invoices in
     * their department — this is the "upfront payment" workflow.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Cancelled invoices are always read-only
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return false;
        }

        // Paid invoices: only lab/rad staff (or Owner) may update work fields
        if ($invoice->isPaid()) {
            if ($user->hasRole('Owner')) {
                return true;
            }
            if ($user->hasRole('Laboratory') && $invoice->department === 'lab') {
                return true;
            }
            if ($user->hasRole('Radiology') && $invoice->department === 'radiology') {
                return true;
            }
            return false;
        }

        // Owner can update all non-final invoices
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Receptionist can update invoices not yet in progress
        if ($user->hasRole('Receptionist') && $invoice->status === Invoice::STATUS_PENDING) {
            return true;
        }

        // Department staff can update in_progress invoices (e.g. saving reports)
        if ($invoice->status === Invoice::STATUS_IN_PROGRESS) {
            if ($user->hasRole('Laboratory') && $invoice->department === 'lab') {
                return true;
            }
            if ($user->hasRole('Radiology') && $invoice->department === 'radiology') {
                return true;
            }
            if ($user->hasRole('Pharmacy') && $invoice->department === 'pharmacy') {
                return true;
            }
        }

        // Department staff cannot modify invoice details otherwise
        return false;
    }

    /**
     * Determine if user can delete invoice (not if paid/completed).
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Only Owner can delete
        if (!$user->hasRole('Owner')) {
            return false;
        }

        // Cannot delete paid invoices
        if ($invoice->isPaid()) {
            return false;
        }

        // Cannot delete cancelled invoices
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return false;
        }

        // Cannot delete completed invoices
        if ($invoice->status === Invoice::STATUS_COMPLETED) {
            return false;
        }

        return true;
    }

    /**
     * Determine if user can transition invoice status.
     */
    public function transitionStatus(User $user, Invoice $invoice): bool
    {
        // Cannot transition if cancelled
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return false;
        }

        // Paid invoices: lab/rad may "start work" / "complete" on paid invoices
        if ($invoice->status === Invoice::STATUS_PAID) {
            if ($user->hasRole('Owner')) {
                return true;
            }
            if ($user->hasRole('Laboratory') && $invoice->department === 'lab') {
                return true;
            }
            if ($user->hasRole('Radiology') && $invoice->department === 'radiology') {
                return true;
            }
            return false;
        }

        // Owner can transition any invoice
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Receptionist can only mark as paid
        if ($user->hasRole('Receptionist')) {
            return true;
        }

        // Department staff can transition within their department
        if ($user->hasRole('Laboratory') && $invoice->department === 'lab') {
            return true;
        }

        if ($user->hasRole('Radiology') && $invoice->department === 'radiology') {
            return true;
        }

        if ($user->hasRole('Pharmacy') && $invoice->department === 'pharmacy') {
            return true;
        }

        return false;
    }
}
