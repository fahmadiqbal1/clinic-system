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
     * Lab/Rad may update work fields (report_text, results, performed_by) on any
     * non-cancelled invoice in their department — both upfront-payment (paid) and
     * legacy (pending → in_progress) workflows must be supported.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Cancelled invoices are always read-only
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return false;
        }

        // Owner can update any non-cancelled invoice
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Lab staff can update any non-cancelled lab invoice (results, reports)
        if ($user->hasRole('Laboratory') && $invoice->department === 'lab') {
            return true;
        }

        // Radiology staff can update any non-cancelled radiology invoice
        if ($user->hasRole('Radiology') && $invoice->department === 'radiology') {
            return true;
        }

        // Receptionist can update pending invoices (editing before work starts)
        if ($user->hasRole('Receptionist') && $invoice->status === Invoice::STATUS_PENDING) {
            return true;
        }

        // Pharmacy can update in_progress and paid invoices in their department
        if ($user->hasRole('Pharmacy') && $invoice->department === 'pharmacy') {
            return in_array($invoice->status, [
                Invoice::STATUS_PENDING,
                Invoice::STATUS_IN_PROGRESS,
                Invoice::STATUS_PAID,
            ]);
        }

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

        // Paid invoices: lab/rad/pharmacy may "start work" / "complete" on paid invoices
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
            if ($user->hasRole('Pharmacy') && $invoice->department === 'pharmacy') {
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
