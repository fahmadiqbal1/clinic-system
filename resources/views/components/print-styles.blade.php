<style>
    @media print {
        /* ── Page Setup ── */
        @page { size: A4 portrait; margin: 6mm 8mm; }

        /* ── Reset everything to white/black ── */
        body, html {
            background: #fff !important;
            color: #000 !important;
            font-size: 10px !important;
            line-height: 1.25 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Hide all non-content chrome ── */
        .navbar, .skip-link, .glass-toast-container,
        .no-print, .btn, .quick-actions,
        #glassConfirmModal, .introjs-overlay,
        .breadcrumb, form, .alert, details,
        .page-header, .sidebar, footer,
        .dropdown-menu, .offcanvas, .modal,
        .page-subtitle,
        [data-no-disable],
        script, noscript {
            display: none !important;
        }

        /* ── Print Header — visible only in print ── */
        .print-header {
            display: block !important;
            text-align: center;
            margin: 0 0 4px !important;
            padding: 0 0 3px !important;
            border-bottom: 2px solid #000;
        }
        .print-header h2 {
            margin: 0 !important;
            font-size: 14px !important;
            font-weight: bold !important;
            color: #000 !important;
        }
        .print-header p {
            margin: 2px 0 0 !important;
            font-size: 10px !important;
            color: #444 !important;
        }

        /* ── Layout resets ── */
        main {
            padding: 0 !important;
            margin: 0 !important;
            min-height: auto !important;
        }
        .container, .container-fluid, .container-lg {
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .glass-card, .card {
            background: #fff !important;
            color: #000 !important;
            border: none !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            padding: 0 !important;
            margin: 0 0 3px !important;
            border-radius: 0 !important;
        }
        .card-header {
            background: #f5f5f5 !important;
            color: #000 !important;
            border: none !important;
            padding: 3px 6px !important;
            font-size: 11px !important;
            font-weight: bold !important;
        }
        .card-body {
            padding: 4px 6px !important;
        }

        /* ── Spacing — aggressively compact ── */
        .mt-4, .mt-3, .mt-2 { margin-top: 2px !important; }
        .mb-4, .mb-3, .mb-2, .mb-1 { margin-bottom: 2px !important; }
        .pt-4, .pt-3, .pt-2 { padding-top: 2px !important; }
        .pb-4, .pb-3, .pb-2 { padding-bottom: 2px !important; }
        .p-2, .p-3 { padding: 2px !important; }
        .gap-2, .gap-3, .g-2, .g-3 { gap: 2px !important; }
        .me-1, .me-2, .me-3 { margin-right: 2px !important; }

        /* ── Row/column compaction ── */
        .row { margin-left: 0 !important; margin-right: 0 !important; }
        [class*="col-md-"] { padding: 1px 4px !important; }

        /* ── Typography ── */
        h1, .h1 { font-size: 14px !important; }
        h2, .h2, .h3 { font-size: 12px !important; }
        h3, .h4, h4 { font-size: 11px !important; }
        h5, .h5 { font-size: 10px !important; }
        h6, .h6 { font-size: 9px !important; }
        h1, h2, h3, h4, h5, h6 {
            color: #000 !important;
            margin-bottom: 1px !important;
            margin-top: 1px !important;
        }
        p { margin-bottom: 1px !important; font-size: 10px !important; }
        .small, small { font-size: 8px !important; }
        .fs-5 { font-size: 11px !important; }
        .fw-semibold, .fw-bold { color: #000 !important; }

        /* ── Force black text on glass theme ── */
        .text-white, .text-white-50, .text-muted,
        [style*="color:var(--text-muted)"],
        [style*="color:var(--accent-"],
        .info-label, .info-value, .stat-value, .stat-label,
        .page-subtitle, .data-label, .data-value,
        .code-tag, .font-monospace {
            color: #000 !important;
            text-shadow: none !important;
        }
        [style*="color:var(--text-muted)"] { color: #555 !important; }
        .info-label { color: #555 !important; font-size: 8px !important; }
        .info-value, .stat-value { color: #000 !important; font-size: 10px !important; }

        /* ── Info grid compaction ── */
        .info-grid {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 2px 8px !important;
        }
        .info-grid-item { padding: 1px 0 !important; }

        /* ── Table compaction ── */
        .table-responsive { overflow: visible !important; }
        .table { font-size: 9px !important; }
        .table th, .table td {
            color: #000 !important;
            border-color: #bbb !important;
            padding: 2px 4px !important;
            font-size: 9px !important;
        }
        .table th {
            background: #eee !important;
            font-weight: bold !important;
        }

        /* ── Badge compaction ── */
        .badge, [class*="badge-glass"] {
            border: 1px solid #888 !important;
            color: #000 !important;
            background: #eee !important;
            font-size: 8px !important;
            padding: 0 3px !important;
            border-radius: 2px !important;
        }

        /* ── Section dividers ── */
        [style*="border-bottom:1px solid"], [style*="border-top:1px solid"] {
            border-color: #ccc !important;
        }

        /* ── Icon compaction ── */
        .stat-icon { display: none !important; }
        i[class*="bi-"] { font-size: inherit !important; }

        /* ── Hide animation classes ── */
        .fade-in { opacity: 1 !important; animation: none !important; }

        /* ── QR code ── */
        #fbr-qr-container {
            border: 1px solid #ccc !important;
            background: #fff !important;
            padding: 4px !important;
        }

        /* ── Footer print line ── */
        .print-footer {
            display: block !important;
            text-align: center;
            border-top: 1px solid #ccc;
            margin-top: 4px;
            padding-top: 3px;
            font-size: 8px;
            color: #666;
        }
    }
</style>
