<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

        <title>@yield('title', config('app.name', 'Aviva HealthCare'))</title>

        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Google Fonts - Inter -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        
        <!-- Glass Prism Design System -->
        <link href="{{ asset('css/clinic-glass.css') }}" rel="stylesheet">
        
        <!-- Intro.js CSS -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/introjs.min.css" rel="stylesheet">

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>

        @stack('styles')

        <!-- Theme initialization (prevent flash) -->
        <script>
            (function() {
                var theme = localStorage.getItem('clinic-theme') || 'dark';
                document.documentElement.setAttribute('data-theme', theme);
            })();
        </script>
    </head>
    <body data-role="{{ strtolower(Auth::user()->roles->first()?->name ?? 'user') }}">
        <!-- Skip to main content (accessibility) -->
        <a href="#main-content" class="skip-link">Skip to main content</a>
        @if (Auth::check())
        <nav class="navbar navbar-expand-lg navbar-dark" aria-label="Main navigation">
            <div class="container-fluid">
                <a class="navbar-brand" href="{{ route('home') }}">
                    <span class="brand-icon">
                        <i class="bi bi-heart-pulse"></i>
                    </span>
                    <span class="brand-text"><span>Aviva</span> HealthCare</span>
                </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        @if(Auth::user()->hasRole('Owner'))
                            <li class="nav-item"><a class="nav-link nav-role-owner" href="{{ route('owner.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('owner.users.index') }}">Users</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('owner.service-catalog.index') }}">Services</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('owner.expenses.index') }}">Expenses</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('owner.payouts.index') }}">Payouts</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('contracts.index') }}">Contracts</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-wallet2 me-1"></i>Finance</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('owner.revenue-ledger.index') }}"><i class="bi bi-journal-text me-2"></i>Revenue Ledger</a></li>
                                    <li><a class="dropdown-item" href="{{ route('owner.zakat.index') }}"><i class="bi bi-moon-stars me-2"></i>Zakat Calculator</a></li>
                                    <li><a class="dropdown-item" href="{{ route('owner.financial-report') }}"><i class="bi bi-file-earmark-bar-graph me-2"></i>Financial Report</a></li>
                                    <li><a class="dropdown-item" href="{{ route('owner.department-pnl') }}"><i class="bi bi-building me-2"></i>Department P&L</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-graph-up me-1"></i>Intelligence</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('owner.discount-approvals.index') }}"><i class="bi bi-tag me-2"></i>Discount Approvals</a></li>
                                    <li><a class="dropdown-item" href="{{ route('owner.expense-intelligence') }}"><i class="bi bi-lightbulb me-2"></i>Expense Intelligence</a></li>
                                    <li><a class="dropdown-item" href="{{ route('owner.inventory-health') }}"><i class="bi bi-heart-pulse me-2"></i>Inventory Health</a></li>
                                    <li><a class="dropdown-item" href="{{ route('owner.procurement-pipeline') }}"><i class="bi bi-funnel me-2"></i>Procurement Pipeline</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('owner.activity-feed') }}"><i class="bi bi-clock-history me-2"></i>Activity Feed</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('owner.platform-settings.index') }}">
                                    <i class="bi bi-cpu me-1"></i>AI Platforms
                                </a>
                            </li>
                        @endif

                        @if(Auth::user()->hasRole('Receptionist'))
                            <li class="nav-item"><a class="nav-link nav-role-receptionist" href="{{ route('receptionist.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('receptionist.patients.index') }}">Patients</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('receptionist.invoices.index') }}">Invoices</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Payouts</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('receptionist.payouts.dashboard') }}"><i class="bi bi-cash me-2"></i>Quick Payout</a></li>
                                    <li><a class="dropdown-item" href="{{ route('reception.payouts.index') }}"><i class="bi bi-list me-2"></i>All Payouts</a></li>
                                    <li><a class="dropdown-item" href="{{ route('reception.payouts.create') }}"><i class="bi bi-plus-circle me-2"></i>Custom Payout</a></li>
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('contracts.show') }}">My Contract</a></li>
                        @endif

                        @if(Auth::user()->hasRole('Doctor'))
                            <li class="nav-item"><a class="nav-link nav-role-doctor" href="{{ route('doctor.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('doctor.patients.index') }}">Patients</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('doctor.prescriptions.index') }}">Prescriptions</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('doctor.invoices.index') }}">Invoices</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('reception.payouts.index') }}">Payouts</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('contracts.show') }}">Contract</a></li>
                        @endif

                        @if(Auth::user()->hasRole('Triage'))
                            <li class="nav-item"><a class="nav-link nav-role-triage" href="{{ route('triage.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('triage.patients.index') }}">Queue</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('contracts.show') }}">My Contract</a></li>
                        @endif

                        @if(Auth::user()->hasRole('Laboratory'))
                            <li class="nav-item"><a class="nav-link nav-role-laboratory" href="{{ route('laboratory.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('laboratory.invoices.index') }}">Tests</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('laboratory.catalog.index') }}">Catalog</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('laboratory.equipment.index') }}">Equipment</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('contracts.show') }}">My Contract</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-box-seam me-1"></i>Inventory</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('inventory.index') }}"><i class="bi bi-boxes me-2"></i>Stock Items</a></li>
                                    <li><a class="dropdown-item" href="{{ route('stock-movements.index') }}"><i class="bi bi-arrow-left-right me-2"></i>Stock Movements</a></li>
                                    <li><a class="dropdown-item" href="{{ route('procurement.index') }}"><i class="bi bi-cart3 me-2"></i>Procurement</a></li>
                                </ul>
                            </li>
                        @endif

                        @if(Auth::user()->hasRole('Radiology'))
                            <li class="nav-item"><a class="nav-link nav-role-radiology" href="{{ route('radiology.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('radiology.invoices.index') }}">Imaging</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('radiology.catalog.index') }}">Catalog</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('radiology.equipment.index') }}">Equipment</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('contracts.show') }}">My Contract</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-box-seam me-1"></i>Inventory</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('inventory.index') }}"><i class="bi bi-boxes me-2"></i>Stock Items</a></li>
                                    <li><a class="dropdown-item" href="{{ route('stock-movements.index') }}"><i class="bi bi-arrow-left-right me-2"></i>Stock Movements</a></li>
                                    <li><a class="dropdown-item" href="{{ route('procurement.index') }}"><i class="bi bi-cart3 me-2"></i>Procurement</a></li>
                                </ul>
                            </li>
                        @endif

                        @if(Auth::user()->hasRole('Pharmacy'))
                            <li class="nav-item"><a class="nav-link nav-role-pharmacy" href="{{ route('pharmacy.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('pharmacy.invoices.index') }}">Orders</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('pharmacy.prescriptions.index') }}">Prescriptions</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('contracts.show') }}">My Contract</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-box-seam me-1"></i>Inventory</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('inventory.index') }}"><i class="bi bi-boxes me-2"></i>Stock Items</a></li>
                                    <li><a class="dropdown-item" href="{{ route('stock-movements.index') }}"><i class="bi bi-arrow-left-right me-2"></i>Stock Movements</a></li>
                                    <li><a class="dropdown-item" href="{{ route('procurement.index') }}"><i class="bi bi-cart3 me-2"></i>Procurement</a></li>
                                </ul>
                            </li>
                        @endif

                        @if(Auth::user()->hasRole('Patient'))
                            <li class="nav-item"><a class="nav-link nav-role-patient" href="{{ route('patient.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>My Health</a></li>
                        @endif
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        {{-- Theme Toggle --}}
                        <li class="nav-item d-flex align-items-center me-2">
                            <button class="theme-toggle-btn" id="themeToggle" title="Toggle light/dark theme (Alt+T)">
                                <i class="bi bi-sun-fill" id="themeIcon"></i>
                            </button>
                        </li>

                        {{-- Keyboard Shortcuts Help --}}
                        <li class="nav-item d-flex align-items-center me-2">
                            <button class="theme-toggle-btn" id="shortcutsBtn" title="Keyboard shortcuts (?)">
                                <i class="bi bi-keyboard"></i>
                            </button>
                        </li>

                        {{-- Notification Bell --}}
                        @php
                            $unreadNotifications = Auth::user()->unreadNotifications->take(10);
                            $unreadCount = Auth::user()->unreadNotifications->count();
                        @endphp
                        <li class="nav-item dropdown" id="notificationBell">
                            <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                                <i class="bi bi-bell{{ $unreadCount > 0 ? '-fill' : '' }}" id="bellIcon"></i>
                                @if($unreadCount > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;" id="bellBadge">
                                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                                    </span>
                                @else
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem; display:none;" id="bellBadge"></span>
                                @endif
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width:360px; max-height:450px; overflow-y:auto;" id="notificationDropdown">
                                <li><h6 class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span>Notifications</span>
                                    <small class="text-muted" id="notifRefreshStatus"></small>
                                </h6></li>
                                <li id="notificationList">
                                @forelse($unreadNotifications as $notif)
                                    <a class="dropdown-item d-flex align-items-start gap-2 py-2 notif-item" href="{{ $notif->data['url'] ?? '#' }}" data-notif-id="{{ $notif->id }}" onclick="fetch('/notifications/{{ $notif->id }}/read', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}})">
                                        <i class="bi {{ $notif->data['icon'] ?? 'bi-bell' }} mt-1" style="color:var(--accent-{{ $notif->data['color'] ?? 'primary' }}); font-size:1.1rem;"></i>
                                        <div class="flex-fill">
                                            <strong class="d-block" style="font-size:0.82rem;">{{ $notif->data['title'] ?? 'Notification' }}</strong>
                                            <small style="color:var(--text-secondary);">{{ Str::limit($notif->data['message'] ?? '', 80) }}</small>
                                            <div class="d-flex align-items-center mt-1">
                                                <i class="bi bi-clock me-1" style="font-size:0.65rem; color:var(--accent-warning);"></i>
                                                <small class="notif-timer fw-semibold" data-assigned-at="{{ $notif->data['assigned_at'] ?? $notif->created_at->toIso8601String() }}" style="font-size:0.72rem; color:var(--accent-warning);"></small>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <span class="dropdown-item text-muted text-center py-3" id="noNotifMsg"><i class="bi bi-check-circle me-1"></i>All caught up!</span>
                                @endforelse
                                </li>
                                @if($unreadCount > 0)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="/notifications/mark-all-read" method="POST" class="px-3 py-1">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Mark all as read</button>
                                        </form>
                                    </li>
                                @endif
                            </ul>
                        </li>

                        {{-- User Menu --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item text-muted">{{ Auth::user()->roles->first()?->name ?? 'User' }}</span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        @endif

        <main id="main-content" role="main">
            {{-- Global Toast Container --}}
            <div id="glass-toast-container" class="glass-toast-container" aria-live="polite" aria-atomic="true">
                @if(session('success'))
                    <div class="glass-toast glass-toast-success fade-in" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <span>{{ session('success') }}</span>
                        <button type="button" class="glass-toast-close" aria-label="Close">&times;</button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="glass-toast glass-toast-danger fade-in" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span>{{ session('error') }}</span>
                        <button type="button" class="glass-toast-close" aria-label="Close">&times;</button>
                    </div>
                @endif
                @if(session('warning'))
                    <div class="glass-toast glass-toast-warning fade-in" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <span>{{ session('warning') }}</span>
                        <button type="button" class="glass-toast-close" aria-label="Close">&times;</button>
                    </div>
                @endif
                @if(session('info'))
                    <div class="glass-toast glass-toast-info fade-in" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <span>{{ session('info') }}</span>
                        <button type="button" class="glass-toast-close" aria-label="Close">&times;</button>
                    </div>
                @endif
            </div>

            @yield('content')
        </main>

        {{-- Glass Confirmation Modal --}}
        <div class="modal fade" id="glassConfirmModal" tabindex="-1" aria-labelledby="glassConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-card" style="border-radius:var(--card-radius);">
                    <div class="modal-header" style="border-bottom:1px solid var(--glass-border);">
                        <h5 class="modal-title" id="glassConfirmTitle"><i class="bi bi-shield-check me-2"></i>Confirm Action</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="glassConfirmBody" style="color:var(--text-secondary);">
                        Are you sure?
                    </div>
                    <div class="modal-footer" style="border-top:1px solid var(--glass-border);">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="glassConfirmBtn">Confirm</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Command Palette --}}
        <div class="command-palette-backdrop" id="cmdPaletteBackdrop"></div>
        <div class="command-palette" id="cmdPalette">
            <input type="text" class="command-palette-input" id="cmdPaletteInput" placeholder="Search patients, invoices, or type a command..." autocomplete="off" />
            <div class="command-palette-results" id="cmdPaletteResults"></div>
            <div class="command-palette-footer">
                <span><kbd>&uarr;&darr;</kbd> Navigate</span>
                <span><kbd>Enter</kbd> Open</span>
                <span><kbd>Esc</kbd> Close</span>
            </div>
        </div>

        {{-- Shortcuts Help Modal --}}
        <div class="modal fade" id="shortcutsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-card" style="border-radius:var(--card-radius);">
                    <div class="modal-header" style="border-bottom:1px solid var(--glass-border);">
                        <h5 class="modal-title"><i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="shortcuts-grid">
                            <div class="shortcut-item"><span>Command Palette</span><kbd>Ctrl+K</kbd></div>
                            <div class="shortcut-item"><span>Toggle Theme</span><kbd>Alt+T</kbd></div>
                            <div class="shortcut-item"><span>Dashboard</span><kbd>Alt+D</kbd></div>
                            <div class="shortcut-item"><span>Notifications</span><kbd>Alt+N</kbd></div>
                            <div class="shortcut-item"><span>Shortcuts Help</span><kbd>?</kbd></div>
                            <div class="shortcut-item"><span>Start Tour</span><kbd>Alt+G</kbd></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- Intro.js -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/intro.min.js"></script>

        {{-- ── Global JS: Toast auto-dismiss, double-submit prevention, glass confirm ── --}}
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            /* ── Toast auto-dismiss ── */
            document.querySelectorAll('.glass-toast').forEach(function(toast) {
                // Close button
                var closeBtn = toast.querySelector('.glass-toast-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(120%)';
                        setTimeout(function() { toast.remove(); }, 300);
                    });
                }
                // Auto-dismiss after 5s
                setTimeout(function() {
                    if (toast.parentNode) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(120%)';
                        setTimeout(function() { toast.remove(); }, 300);
                    }
                }, 5000);
            });

            /* ── Form double-submit prevention ── */
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function() {
                    var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    btns.forEach(function(btn) {
                        if (btn.dataset.noDisable) return;
                        btn.disabled = true;
                        btn.classList.add('btn-loading');
                        var origHtml = btn.innerHTML;
                        btn.dataset.origHtml = origHtml;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Processing...';
                        // Re-enable after 6s as safety net
                        setTimeout(function() {
                            btn.disabled = false;
                            btn.classList.remove('btn-loading');
                            btn.innerHTML = origHtml;
                        }, 6000);
                    });
                });
            });

            /* ── Glass Confirm Modal ── */
            var confirmModal = document.getElementById('glassConfirmModal');
            if (confirmModal) {
                var bsModal = new bootstrap.Modal(confirmModal);
                var confirmBtn = document.getElementById('glassConfirmBtn');
                var confirmBody = document.getElementById('glassConfirmBody');
                var pendingForm = null;

                document.querySelectorAll('[data-confirm]').forEach(function(el) {
                    el.addEventListener('click', function(e) {
                        e.preventDefault();
                        var message = el.getAttribute('data-confirm');
                        confirmBody.textContent = message;

                        // Determine the form to submit
                        if (el.tagName === 'FORM') {
                            pendingForm = el;
                        } else if (el.closest('form')) {
                            pendingForm = el.closest('form');
                        } else if (el.href) {
                            pendingForm = { _link: el.href };
                        }

                        bsModal.show();
                    });
                });

                if (confirmBtn) {
                    confirmBtn.addEventListener('click', function() {
                        bsModal.hide();
                        if (pendingForm) {
                            if (pendingForm._link) {
                                window.location.href = pendingForm._link;
                            } else {
                                pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
                            }
                            pendingForm = null;
                        }
                    });
                }
            }

            /* ── Sortable Table Columns ── */
            document.querySelectorAll('.sortable-th').forEach(function(th) {
                th.setAttribute('role', 'columnheader');
                th.setAttribute('aria-sort', 'none');
                th.setAttribute('tabindex', '0');
                th.addEventListener('click', handleSort);
                th.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSort.call(th); }
                });
            });

            function handleSort() {
                var th = this;
                    var table = th.closest('table');
                    var tbody = table.querySelector('tbody');
                    var idx = Array.from(th.parentNode.children).indexOf(th);
                    var rows = Array.from(tbody.querySelectorAll('tr'));
                    var asc = !th.classList.contains('sort-asc');

                    // Reset all headers in this table
                    th.parentNode.querySelectorAll('.sortable-th').forEach(function(h) {
                        h.classList.remove('sort-asc', 'sort-desc');
                        h.setAttribute('aria-sort', 'none');
                    });
                    th.classList.add(asc ? 'sort-asc' : 'sort-desc');
                    th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');

                    rows.sort(function(a, b) {
                        var aText = (a.children[idx]?.textContent || '').trim();
                        var bText = (b.children[idx]?.textContent || '').trim();
                        var aNum = parseFloat(aText.replace(/[^0-9.-]/g, ''));
                        var bNum = parseFloat(bText.replace(/[^0-9.-]/g, ''));
                        if (!isNaN(aNum) && !isNaN(bNum)) return asc ? aNum - bNum : bNum - aNum;
                        return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
                    });
                    rows.forEach(function(row) { tbody.appendChild(row); });
            }

            /* ── Theme Toggle ── */
            var themeToggle = document.getElementById('themeToggle');
            var themeIcon = document.getElementById('themeIcon');
            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('clinic-theme', theme);
                if (themeIcon) {
                    themeIcon.className = theme === 'light' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
                }
            }
            if (themeToggle) {
                var currentTheme = localStorage.getItem('clinic-theme') || 'dark';
                applyTheme(currentTheme);
                themeToggle.addEventListener('click', function() {
                    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    applyTheme(next);
                });
            }

            /* ── Command Palette ── */
            var cmdPalette = document.getElementById('cmdPalette');
            var cmdBackdrop = document.getElementById('cmdPaletteBackdrop');
            var cmdInput = document.getElementById('cmdPaletteInput');
            var cmdResults = document.getElementById('cmdPaletteResults');
            var cmdActiveIdx = -1;
            var cmdSearchTimer = null;

            function openCommandPalette() {
                if (!cmdPalette) return;
                cmdPalette.classList.add('active');
                cmdBackdrop.classList.add('active');
                cmdInput.value = '';
                cmdResults.innerHTML = buildQuickActions();
                cmdActiveIdx = -1;
                setTimeout(function() { cmdInput.focus(); }, 50);
            }
            function closeCommandPalette() {
                if (!cmdPalette) return;
                cmdPalette.classList.remove('active');
                cmdBackdrop.classList.remove('active');
            }

            function buildQuickActions() {
                var role = document.body.getAttribute('data-role') || '';
                var actions = [
                    { icon: 'bi-speedometer2', label: 'Go to Dashboard', url: '/' + role + '/dashboard' },
                ];
                if (role === 'owner' || role === 'receptionist') {
                    actions.push({ icon: 'bi-person-plus', label: 'Register Patient', url: '/receptionist/patients/create' });
                    actions.push({ icon: 'bi-receipt', label: 'Create Invoice', url: '/receptionist/invoices/create' });
                }
                if (role === 'owner') {
                    actions.push({ icon: 'bi-building', label: 'Department P&L', url: '/owner/department-pnl' });
                    actions.push({ icon: 'bi-graph-up-arrow', label: 'Financial Report', url: '/owner/financial-report' });
                    actions.push({ icon: 'bi-receipt', label: 'Expense Management', url: '/owner/expenses' });
                    actions.push({ icon: 'bi-clock-history', label: 'Activity Feed', url: '/owner/activity-feed' });
                }
                var html = '<div class="px-2 py-1"><small class="text-muted text-uppercase" style="font-size:0.7rem;">Quick Actions</small></div>';
                actions.forEach(function(a) {
                    html += '<a class="command-palette-item" href="' + a.url + '"><i class="bi ' + a.icon + '"></i><span>' + a.label + '</span></a>';
                });
                return html;
            }

            if (cmdInput) {
                cmdInput.addEventListener('input', function() {
                    var q = cmdInput.value.trim();
                    if (q.length < 2) {
                        cmdResults.innerHTML = buildQuickActions();
                        cmdActiveIdx = -1;
                        return;
                    }
                    clearTimeout(cmdSearchTimer);
                    cmdSearchTimer = setTimeout(function() {
                        cmdResults.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
                        fetch('/search/global?q=' + encodeURIComponent(q), {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
                        })
                        .then(function(r) { return r.ok ? r.json() : { results: [] }; })
                        .then(function(data) {
                            if (!data.results || data.results.length === 0) {
                                cmdResults.innerHTML = '<div class="text-center py-3 text-muted"><i class="bi bi-search me-1"></i>No results for "' + q + '"</div>';
                                return;
                            }
                            var html = '';
                            data.results.forEach(function(r) {
                                html += '<a class="command-palette-item" href="' + r.url + '"><i class="bi ' + (r.icon || 'bi-link-45deg') + '"></i><div><div class="fw-medium">' + r.title + '</div><small class="text-muted">' + (r.subtitle || '') + '</small></div></a>';
                            });
                            cmdResults.innerHTML = html;
                            cmdActiveIdx = -1;
                        })
                        .catch(function() {
                            cmdResults.innerHTML = buildQuickActions();
                        });
                    }, 300);
                });

                cmdInput.addEventListener('keydown', function(e) {
                    var items = cmdResults.querySelectorAll('.command-palette-item');
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        cmdActiveIdx = Math.min(cmdActiveIdx + 1, items.length - 1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        cmdActiveIdx = Math.max(cmdActiveIdx - 1, 0);
                    } else if (e.key === 'Enter' && cmdActiveIdx >= 0 && items[cmdActiveIdx]) {
                        e.preventDefault();
                        items[cmdActiveIdx].click();
                        return;
                    } else if (e.key === 'Escape') {
                        closeCommandPalette();
                        return;
                    }
                    items.forEach(function(it, i) { it.classList.toggle('active', i === cmdActiveIdx); });
                });
            }
            if (cmdBackdrop) cmdBackdrop.addEventListener('click', closeCommandPalette);

            /* ── Keyboard Shortcuts ── */
            document.addEventListener('keydown', function(e) {
                // Don't trigger when typing in inputs
                var tag = (e.target.tagName || '').toLowerCase();
                var isInput = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;

                // Ctrl+K — Command Palette (always)
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    if (cmdPalette && cmdPalette.classList.contains('active')) {
                        closeCommandPalette();
                    } else {
                        openCommandPalette();
                    }
                    return;
                }

                // Escape closes palette
                if (e.key === 'Escape' && cmdPalette && cmdPalette.classList.contains('active')) {
                    closeCommandPalette();
                    return;
                }

                if (isInput) return;

                // Alt+T — Toggle theme
                if (e.altKey && e.key === 't') {
                    e.preventDefault();
                    if (themeToggle) themeToggle.click();
                    return;
                }

                // Alt+D — Dashboard
                if (e.altKey && e.key === 'd') {
                    e.preventDefault();
                    var role = document.body.getAttribute('data-role');
                    if (role) window.location.href = '/' + role + '/dashboard';
                    return;
                }

                // Alt+N — Toggle notifications
                if (e.altKey && e.key === 'n') {
                    e.preventDefault();
                    var bellDropdown = document.querySelector('#notificationBell .nav-link');
                    if (bellDropdown) bellDropdown.click();
                    return;
                }

                // Alt+G — Start guided tour
                if (e.altKey && e.key === 'g') {
                    e.preventDefault();
                    if (typeof introJs !== 'undefined') startGuidedTour();
                    return;
                }

                // ? — Shortcuts help
                if (e.key === '?' || (e.shiftKey && e.key === '/')) {
                    e.preventDefault();
                    var shortcutsModal = document.getElementById('shortcutsModal');
                    if (shortcutsModal) {
                        new bootstrap.Modal(shortcutsModal).show();
                    }
                }
            });

            // Shortcuts button
            var shortcutsBtn = document.getElementById('shortcutsBtn');
            if (shortcutsBtn) {
                shortcutsBtn.addEventListener('click', function() {
                    var shortcutsModal = document.getElementById('shortcutsModal');
                    if (shortcutsModal) new bootstrap.Modal(shortcutsModal).show();
                });
            }
        });
        </script>

        @stack('scripts')

        {{-- ── Notification Live Timer ── --}}
        <script>
        (function() {
            function updateTimers() {
                document.querySelectorAll('.notif-timer').forEach(function(el) {
                    var assignedAt = el.getAttribute('data-assigned-at');
                    if (!assignedAt) return;
                    var then = new Date(assignedAt).getTime();
                    var now = Date.now();
                    var diffSec = Math.max(0, Math.floor((now - then) / 1000));
                    var h = Math.floor(diffSec / 3600);
                    var m = Math.floor((diffSec % 3600) / 60);
                    var s = diffSec % 60;
                    var parts = [];
                    if (h > 0) parts.push(h + 'h');
                    parts.push(m + 'm');
                    parts.push(s + 's');
                    el.textContent = parts.join(' ') + ' ago';
                    // Color escalation: green < 3min, yellow < 10min, orange < 20min, red >= 20min
                    if (diffSec < 180) {
                        el.style.color = 'var(--accent-success)';
                    } else if (diffSec < 600) {
                        el.style.color = 'var(--accent-warning)';
                    } else if (diffSec < 1200) {
                        el.style.color = '#fd7e14';
                    } else {
                        el.style.color = 'var(--accent-danger)';
                    }
                });
            }
            // Update timers every second
            updateTimers();
            setInterval(updateTimers, 1000);

            // Poll for new notifications every 30 seconds
            setInterval(function() {
                fetch('/notifications/unread', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
                })
                .then(function(r) { return r.ok ? r.json() : null; })
                .then(function(data) {
                    if (!data) return;
                    var badge = document.getElementById('bellBadge');
                    var icon = document.getElementById('bellIcon');
                    if (badge) {
                        if (data.count > 0) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = '';
                            if (icon) { icon.className = 'bi bi-bell-fill'; }
                        } else {
                            badge.style.display = 'none';
                            if (icon) { icon.className = 'bi bi-bell'; }
                        }
                    }
                    // Re-render notification items
                    var list = document.getElementById('notificationList');
                    if (list && data.notifications && data.notifications.length > 0) {
                        var html = '';
                        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        data.notifications.forEach(function(n) {
                            html += '<a class="dropdown-item d-flex align-items-start gap-2 py-2 notif-item" href="' + (n.url || '#') + '" data-notif-id="' + n.id + '" onclick="fetch(\'/notifications/' + n.id + '/read\', {method:\'POST\', headers:{\'X-CSRF-TOKEN\':\'' + csrfToken + '\'}})">';
                            html += '<i class="bi ' + (n.icon || 'bi-bell') + ' mt-1" style="color:var(--accent-' + (n.color || 'primary') + '); font-size:1.1rem;"></i>';
                            html += '<div class="flex-fill">';
                            html += '<strong class="d-block" style="font-size:0.82rem;">' + (n.title || 'Notification') + '</strong>';
                            html += '<small style="color:var(--text-secondary);">' + (n.message || '').substring(0, 80) + '</small>';
                            html += '<div class="d-flex align-items-center mt-1">';
                            html += '<i class="bi bi-clock me-1" style="font-size:0.65rem; color:var(--accent-warning);"></i>';
                            html += '<small class="notif-timer fw-semibold" data-assigned-at="' + (n.assigned_at || n.created_at) + '" style="font-size:0.72rem; color:var(--accent-warning);"></small>';
                            html += '</div></div></a>';
                        });
                        list.innerHTML = html;
                        updateTimers();
                    } else if (list && data.count === 0) {
                        list.innerHTML = '<span class="dropdown-item text-muted text-center py-3"><i class="bi bi-check-circle me-1"></i>All caught up!</span>';
                    }
                })
                .catch(function() {}); // Silent fail for polling
            }, 30000);
        })();
        </script>

        {{-- ── Enhanced Guided Tours ── --}}
        <script>
        function startGuidedTour() {
            var intro = introJs();
            var role = '{{ Auth::user()->roles->first()?->name ?? '' }}';
            var tourSteps = [];
            var commonOpts = { exitOnEsc: true, exitOnOverlayClick: true, showBullets: true, showProgress: true, scrollToElement: true, tooltipClass: 'glass-tour-tooltip' };

            if (role === 'Owner') {
                tourSteps = [
                    { element: '.navbar-brand', intro: '<strong>Welcome, Owner!</strong><br>This is your Aviva HealthCare command center.', position: 'bottom' },
                    { element: '#themeToggle', intro: '<strong>Theme Toggle</strong><br>Switch between dark and light mode. Your preference is saved automatically.', position: 'bottom' },
                    { element: '#shortcutsBtn', intro: '<strong>Keyboard Shortcuts</strong><br>Press <kbd>?</kbd> anytime to see all shortcuts. Try <kbd>Ctrl+K</kbd> for the command palette.', position: 'bottom' },
                    { element: '#notificationBell', intro: '<strong>Notifications</strong><br>Real-time alerts for discount requests, low stock, and system events.', position: 'bottom' },
                    { element: '.nav-link[href*="expenses"]', intro: '<strong>Expenses</strong><br>Track fixed (rent, bills) and variable costs by department.', position: 'bottom' },
                    { element: '.dropdown-toggle[data-bs-toggle="dropdown"]', intro: '<strong>Finance & Intelligence</strong><br>Access Department P&L, Revenue Ledger, Expense Intelligence, and more.', position: 'bottom' },
                    { intro: '<strong>Pro Tips</strong><br>• <kbd>Ctrl+K</kbd> opens command palette for quick search<br>• <kbd>Alt+D</kbd> jumps to dashboard<br>• All tables are sortable — click column headers', position: 'center' }
                ];
            } else if (role === 'Receptionist') {
                tourSteps = [
                    { element: '.navbar-brand', intro: '<strong>Welcome, Receptionist!</strong><br>Your hub for patient registration and flow management.', position: 'bottom' },
                    { element: '#themeToggle', intro: '<strong>Theme Toggle</strong><br>Switch between dark and light mode for your comfort.', position: 'bottom' },
                    { element: '.nav-link[href*="patients"]', intro: '<strong>Patients</strong><br>Register new patients and manage existing records.', position: 'bottom' },
                    { element: '.nav-link[href*="invoices"]', intro: '<strong>Invoices</strong><br>Create and manage invoices. Collect payments and request discounts.', position: 'bottom' },
                    { element: '.nav-link[href*="payouts"]', intro: '<strong>Doctor Payouts</strong><br>View daily earnings and create payouts for commission-based doctors.', position: 'bottom' },
                    { element: '#notificationBell', intro: '<strong>Notifications</strong><br>Get alerts when patients arrive, invoices need attention, or prescriptions are created.', position: 'bottom' },
                    { intro: '<strong>Pro Tips</strong><br>• Dashboard auto-refreshes every 60 seconds<br>• Use <kbd>Ctrl+K</kbd> to quickly find patients<br>• Click "Pay" on doctor earnings to create a payout', position: 'center' }
                ];
            } else if (role === 'Doctor') {
                tourSteps = [
                    { element: '.navbar-brand', intro: '<strong>Welcome, Doctor!</strong><br>Your clinical workspace for patient care.', position: 'bottom' },
                    { element: '.nav-link[href*="patients"]', intro: '<strong>Patients</strong><br>View your assigned patients and their medical history.', position: 'bottom' },
                    { element: '.nav-link[href*="prescriptions"]', intro: '<strong>Prescriptions</strong><br>Write and manage prescriptions. They auto-notify the pharmacy.', position: 'bottom' },
                    { element: '.nav-link[href*="invoices"]', intro: '<strong>Invoices</strong><br>Create lab, radiology, pharmacy, or consultation invoices for patients.', position: 'bottom' },
                    { element: '.nav-link[href*="payouts"]', intro: '<strong>Payouts</strong><br>View and confirm your commission payouts.', position: 'bottom' },
                    { element: '#notificationBell', intro: '<strong>Notifications</strong><br>Get alerts when new patients are assigned to you.', position: 'bottom' },
                    { intro: '<strong>Pro Tips</strong><br>• Use <kbd>Ctrl+K</kbd> for quick patient search<br>• Your earnings update in real-time on the dashboard<br>• All tables are sortable', position: 'center' }
                ];
            } else if (role === 'Triage') {
                tourSteps = [
                    { element: '.navbar-brand', intro: '<strong>Welcome, Triage Nurse!</strong><br>Your station for recording patient vitals.', position: 'bottom' },
                    { element: '.nav-link[href*="patients"]', intro: '<strong>Patient Queue</strong><br>View patients awaiting triage. Record vitals (BP, temp, weight, etc.).', position: 'bottom' },
                    { element: '#notificationBell', intro: '<strong>Notifications</strong><br>Get alerts when new patients check in and need triage.', position: 'bottom' },
                    { intro: '<strong>Quick Start</strong><br>• Click a patient from the queue to record vitals<br>• Once vitals are recorded, the patient moves to the doctor\'s queue<br>• Dashboard shows pending triage count', position: 'center' }
                ];
            } else if (role === 'Laboratory') {
                tourSteps = [
                    { element: '.navbar-brand', intro: '<strong>Welcome, Lab Technician!</strong><br>Your workspace for processing lab tests.', position: 'bottom' },
                    { element: '.nav-link[href*="invoices"]', intro: '<strong>Tests</strong><br>View pending lab test orders and upload results.', position: 'bottom' },
                    { element: '.nav-link[href*="catalog"]', intro: '<strong>Catalog</strong><br>Browse available lab tests and their reference ranges.', position: 'bottom' },
                    { element: '.nav-link[href*="equipment"]', intro: '<strong>Equipment</strong><br>Track lab equipment status and maintenance.', position: 'bottom' },
                    { element: '#notificationBell', intro: '<strong>Notifications</strong><br>Get alerts for new test orders.', position: 'bottom' },
                    { intro: '<strong>Pro Tips</strong><br>• Upload structured results for each test item<br>• Track inventory and create procurement requests<br>• Dashboard auto-refreshes for new orders', position: 'center' }
                ];
            } else if (role === 'Radiology') {
                tourSteps = [
                    { element: '.navbar-brand', intro: '<strong>Welcome, Radiologist!</strong><br>Your workspace for processing imaging orders.', position: 'bottom' },
                    { element: '.nav-link[href*="invoices"]', intro: '<strong>Imaging</strong><br>View pending imaging orders, upload images and findings.', position: 'bottom' },
                    { element: '.nav-link[href*="catalog"]', intro: '<strong>Catalog</strong><br>Browse available imaging procedures.', position: 'bottom' },
                    { element: '#notificationBell', intro: '<strong>Notifications</strong><br>Get alerts for new imaging requests.', position: 'bottom' },
                    { intro: '<strong>Pro Tips</strong><br>• Upload DICOM images and write findings for each study<br>• Track equipment maintenance schedules<br>• Manage your department\'s inventory', position: 'center' }
                ];
            } else if (role === 'Pharmacy') {
                tourSteps = [
                    { element: '.navbar-brand', intro: '<strong>Welcome, Pharmacist!</strong><br>Your workspace for dispensing medications.', position: 'bottom' },
                    { element: '.nav-link[href*="invoices"]', intro: '<strong>Orders</strong><br>View pharmacy orders and dispense medications.', position: 'bottom' },
                    { element: '.nav-link[href*="prescriptions"]', intro: '<strong>Prescriptions</strong><br>View doctor prescriptions and prepare medications.', position: 'bottom' },
                    { element: '#notificationBell', intro: '<strong>Notifications</strong><br>Get alerts for new prescriptions and pharmacy orders.', position: 'bottom' },
                    { intro: '<strong>Pro Tips</strong><br>• Track inventory levels and get low-stock alerts<br>• Create procurement requests when stock is low<br>• Dashboard shows pending orders count', position: 'center' }
                ];
            }

            // Filter out steps with missing elements
            var validSteps = tourSteps.filter(function(s) {
                if (!s.element) return true; // center steps always valid
                return document.querySelector(s.element);
            });

            intro.setOptions(Object.assign({}, commonOpts, { steps: validSteps }));

            intro.oncomplete(function() {
                fetch('{{ route('user.complete-tour') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                }).then(function(r) { return r.ok; });
            });

            intro.onexit(function() {
                // Also mark as completed on skip
                fetch('{{ route('user.complete-tour') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                }).then(function(r) { return r.ok; });
            });

            intro.start();
        }

        @if (!Auth::user()->has_completed_tour)
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(startGuidedTour, 500);
            });
        @endif
        </script>
    </body>
</html>
