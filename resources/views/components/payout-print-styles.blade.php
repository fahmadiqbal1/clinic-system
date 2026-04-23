{{--
    Print styles for payout show page.
    Hides all screen content and shows only .print-payout-layout in @media print.
    Usage: @include('components.payout-print-styles')
--}}
@push('styles')
<style>
.print-payout-layout { display: none; }

@media print {
    @page { size: A4 portrait; margin: 8mm 10mm; }

    /* Hide everything screen-related */
    body, html { background: #fff !important; color: #000 !important; font-size: 10px !important; line-height: 1.4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .navbar, .skip-link, .glass-toast-container, .no-print, .quick-actions,
    #glassConfirmModal, .introjs-overlay, .breadcrumb, .sidebar, footer,
    .dropdown-menu, .offcanvas, .modal, .page-subtitle, script, noscript,
    .print-header { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; min-height: auto !important; }
    .container, .container-fluid, .container-lg { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }

    /* Hide the screen glass-card content */
    .glass-card, .card, .fade-in { display: none !important; }

    /* Show only the print layout */
    .print-payout-layout { display: block !important; font-family: 'Segoe UI', Arial, sans-serif; color: #1a1a2e; }

    /* ── Header ── */
    .pp-header { display: flex; align-items: center; margin-bottom: 6px; }
    .pp-header-left { flex: 0 0 auto; }
    .pp-logo { max-width: 110px; max-height: 52px; display: block; }
    .pp-header-center { flex: 1; text-align: center; padding: 0 12px; }
    .pp-clinic-name { font-size: 16px; font-weight: bold; color: #2d6a4f; }
    .pp-clinic-sub { font-size: 9px; color: #5a6a85; margin-top: 1px; }
    .pp-header-right { flex: 0 0 auto; text-align: right; }
    .pp-tag { background: #2d6a4f !important; color: #fff !important; font-size: 12px; font-weight: bold; padding: 4px 14px; text-transform: uppercase; display: inline-block; }
    .pp-divider { border: none; border-top: 2px solid #2d6a4f; margin: 0 0 8px; }

    /* ── Meta blocks ── */
    .pp-meta-row { display: flex; gap: 10px; margin-bottom: 10px; }
    .pp-meta-box { flex: 1; background: #f4f9f6 !important; padding: 6px 8px; border: 1px solid #d0ddd6; }
    .pp-meta-box-right { text-align: right; }
    .pp-meta-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #2d6a4f; margin-bottom: 2px; }
    .pp-meta-val { font-size: 9px; color: #1a1a2e; line-height: 1.5; }

    /* ── Summary boxes ── */
    .pp-summary { display: flex; gap: 6px; margin-bottom: 10px; }
    .pp-summary-item { flex: 1; text-align: center; padding: 6px 4px; background: #f9fafb !important; border: 1px solid #e5e7eb; }
    .pp-summary-num { font-size: 15px; font-weight: bold; color: #1a1a2e; }
    .pp-summary-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #5a6a85; margin-top: 2px; }
    .pp-accent-green { border-bottom: 3px solid #2d6a4f !important; background: #f4f9f6 !important; }
    .pp-accent-blue { border-bottom: 3px solid #1a56a0 !important; background: #f4f7fc !important; }
    .pp-accent-amber { border-bottom: 3px solid #b45309 !important; background: #fffbeb !important; }
    .pp-accent-red { border-bottom: 3px solid #dc2626 !important; }

    /* ── Status badges ── */
    .pp-badge-green { background: #d1fae5 !important; color: #065f46 !important; font-weight: bold; font-size: 8px; padding: 1px 5px; display: inline-block; }
    .pp-badge-amber { background: #fef3c7 !important; color: #92400e !important; font-weight: bold; font-size: 8px; padding: 1px 5px; display: inline-block; }
    .pp-badge-blue { background: #dbeafe !important; color: #1e40af !important; font-weight: bold; font-size: 8px; padding: 1px 5px; display: inline-block; }
    .pp-badge-red { background: #fee2e2 !important; color: #991b1b !important; font-weight: bold; font-size: 8px; padding: 1px 5px; display: inline-block; }

    /* ── Revenue items table ── */
    .pp-items { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .pp-items th { background: #2d6a4f !important; color: #fff !important; font-size: 8px; font-weight: bold; padding: 5px 4px; text-align: left; text-transform: uppercase; }
    .pp-items td { font-size: 9px; padding: 5px 4px; border-bottom: 1px solid #dde4ef; color: #1a1a2e; }
    .pp-items tr:last-child td { border-bottom: none; }
    .pp-alt td { background: #f8faf9 !important; }
    .pp-tr { text-align: right; }

    /* ── Totals ── */
    .pp-totals-row { display: flex; margin-bottom: 8px; }
    .pp-totals-spacer { flex: 1; }
    .pp-totals-box { flex: 0 0 46%; }
    .pp-total-line { display: flex; justify-content: space-between; font-size: 9px; padding: 3px 6px; border-bottom: 1px solid #dde4ef; }
    .pp-total-lbl { color: #5a6a85; }
    .pp-total-val { font-weight: bold; text-align: right; }
    .pp-grand { background: #2d6a4f !important; color: #fff !important; border-bottom: none; }
    .pp-grand .pp-total-lbl, .pp-grand .pp-total-val { color: #fff !important; font-size: 11px; }

    /* ── Audit trail ── */
    .pp-audit { background: #f9fafb !important; padding: 6px 8px; margin-bottom: 10px; border: 1px solid #e5e7eb; }

    /* ── Signature ── */
    .pp-sig-row { display: flex; gap: 20px; margin-top: 24px; }
    .pp-sig { flex: 1; }
    .pp-sig-line { border-top: 1px solid #555; margin-top: 30px; padding-top: 3px; font-size: 8px; color: #5a6a85; }

    /* ── Footer ── */
    .pp-footer { font-size: 7px; color: #5a6a85; text-align: center; border-top: 1px solid #dde4ef; padding-top: 5px; margin-top: 10px; }
}
</style>
@endpush
