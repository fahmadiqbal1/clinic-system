{{--
    Print styles for invoice views.
    Hides screen content, shows .print-invoice-layout matching the DomPDF template.
--}}
<style>
/* ── Screen: hide the print-only layout ── */
.print-invoice-layout { display: none; }

@media print {
    @page { size: A4 portrait; margin: 10mm 12mm; }

    /* ── Hide ALL screen content ── */
    .navbar, .skip-link, .glass-toast-container, .no-print, .quick-actions,
    #glassConfirmModal, .introjs-overlay, .breadcrumb, .sidebar, footer,
    .dropdown-menu, .offcanvas, .modal, .btn, form, .alert, details,
    .page-header, .page-subtitle, [data-no-disable], script, noscript,
    .print-header { display: none !important; }

    /* Hide all cards/glass content — we replace with the print layout */
    .glass-card, .card, .fade-in { display: none !important; }
    /* But keep the FBR section's QR container in DOM for JS */
    #fbr-qr-container { display: none !important; }
    .d-flex.gap-2.no-print { display: none !important; }
    .d-flex.gap-2.fade-in { display: none !important; }

    /* ── Show the print invoice layout ── */
    .print-invoice-layout { display: block !important; }

    /* ── Reset ── */
    body, html {
        background: #fff !important; color: #1a1a2e !important;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        font-size: 10px !important; line-height: 1.4 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    main { padding: 0 !important; margin: 0 !important; min-height: auto !important; }
    .container, .container-fluid, .container-lg { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }

    /* ── Header ── */
    .pi-header {
        display: flex !important; align-items: center; margin-bottom: 8px;
    }
    .pi-header-left { flex: 0 0 auto; }
    .pi-logo { max-width: 100px; max-height: 50px; display: block; }
    .pi-header-center { flex: 1; padding-left: 10px; }
    .pi-clinic-name { font-size: 15px; font-weight: bold; color: #1a56a0; }
    .pi-clinic-sub { font-size: 8px; color: #5a6a85; margin-top: 1px; }
    .pi-header-right { flex: 0 0 auto; text-align: right; }
    .pi-tag {
        background: #1a56a0; color: #fff !important; font-size: 13px; font-weight: bold;
        padding: 4px 14px; text-transform: uppercase; display: inline-block;
    }
    .pi-divider {
        border: none; border-top: 2px solid #1a56a0; margin: 0 0 8px;
    }

    /* ── Meta boxes ── */
    .pi-meta-row { display: flex !important; gap: 12px; margin-bottom: 8px; }
    .pi-meta-box { flex: 1; padding: 6px 8px; background: #f4f7fc !important; }
    .pi-meta-box-right { text-align: right; }
    .pi-meta-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #1a56a0; margin-bottom: 2px; }
    .pi-meta-val { font-size: 9px; color: #1a1a2e; line-height: 1.5; }
    .pi-tc { text-align: center; }
    .pi-tr { text-align: right; }

    /* ── Items table ── */
    .pi-items {
        width: 100%; border-collapse: collapse; margin-bottom: 8px;
    }
    .pi-items th {
        background: #1a56a0 !important; color: #fff !important;
        font-size: 8px; font-weight: bold; padding: 5px 4px;
        text-align: left; text-transform: uppercase;
    }
    .pi-items td {
        font-size: 9px; padding: 5px 4px; border-bottom: 1px solid #dde4ef;
        color: #1a1a2e;
    }
    .pi-items tr:last-child td { border-bottom: none; }
    .pi-alt td { background: #f8faff !important; }

    /* ── Totals ── */
    .pi-totals-row { display: flex !important; margin-bottom: 8px; }
    .pi-totals-spacer { flex: 0 0 54%; }
    .pi-totals-box { flex: 0 0 46%; }
    .pi-total-line {
        display: flex !important; justify-content: space-between;
        font-size: 9px; padding: 3px 6px; border-bottom: 1px solid #dde4ef;
    }
    .pi-total-lbl { color: #5a6a85; }
    .pi-total-val { font-weight: bold; text-align: right; }
    .pi-grand {
        background: #1a56a0 !important; color: #fff !important;
        font-size: 11px; border-bottom: none;
    }
    .pi-grand .pi-total-lbl, .pi-grand .pi-total-val { color: #fff !important; }

    /* ── Badges ── */
    .pi-badge-paid { background: #d1fae5; color: #065f46; font-weight: bold; font-size: 8px; padding: 1px 5px; }
    .pi-badge-pending { background: #fef3c7; color: #92400e; font-weight: bold; font-size: 8px; padding: 1px 5px; }
    .pi-badge-cancelled { background: #fee2e2; color: #991b1b; font-weight: bold; font-size: 8px; padding: 1px 5px; }

    /* ── Referrer ── */
    .pi-referrer { padding: 6px 8px; background: #fff8f0 !important; border: 1px solid #f0d8b5; margin-bottom: 8px; }

    /* ── FBR block ── */
    .pi-fbr { display: flex !important; margin-bottom: 8px; background: #eef4ff !important; border: 1px solid #c3d0ea; }
    .pi-fbr-data { flex: 1; padding: 6px 8px; }
    .pi-fbr-qr {
        flex: 0 0 100px; display: flex !important; align-items: center; justify-content: center;
        padding: 5px; border-left: 1px solid #c3d0ea;
    }
    .pi-fbr-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #1a56a0; margin-bottom: 1px; }
    .pi-fbr-val { font-size: 8px; color: #1a1a2e; margin-bottom: 4px; }

    /* ── Footer ── */
    .pi-footer {
        font-size: 7px; color: #5a6a85; text-align: center;
        border-top: 1px solid #dde4ef; padding-top: 5px; margin-top: 6px;
    }
}
</style>
