# Aviva HealthCare — Clinic ERP

A full-featured, multi-role clinic management system built with **Laravel 12**, Blade, and Vite. Designed for Pakistani private clinics with FBR tax compliance, AI-assisted diagnostics, SMS notifications, Zakat calculation, and a complete financial stack.

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Roles & Portals](#roles--portals)
- [Architecture Overview](#architecture-overview)
- [Quick Start (Local)](#quick-start-local)
- [Default Accounts](#default-accounts)
- [Environment Variables](#environment-variables)
- [Production Deployment](#production-deployment)
- [AI Integration (MedGemma / Ollama)](#ai-integration-medgemma--ollama)
- [SMS Notifications (TextBee)](#sms-notifications-textbee)
- [FBR Tax Compliance](#fbr-tax-compliance)
- [Financial Architecture](#financial-architecture)
- [Queue Workers & Scheduler](#queue-workers--scheduler)
- [MCP Server (Claude AI Integration)](#mcp-server-claude-ai-integration)
- [Security](#security)
- [Project Structure](#project-structure)

---

## Features

### Clinical
- Patient registration with MR number, CNIC (encrypted), and doctor assignment
- Visit queue with triage vitals (BP, temp, pulse, SpO2, weight, height, BMI)
- Doctor consultation workspace with notes, prescriptions, lab/radiology orders
- Appointment scheduling with conflict detection
- Patient check-in kiosk support

### Financial
- Multi-department invoicing: Consultation, Laboratory, Radiology, Pharmacy
- Weighted Average Cost (WAC) inventory ledger
- Revenue distribution engine — splits each payment across roles (doctor commission, lab fee, overhead)
- Doctor payout management with approval workflow
- Staff contract types: salary, commission, hybrid
- Expense tracking (manual + procurement-linked)
- Department P&L dashboard
- Revenue forecasting with trend analysis
- Discount approval workflow (owner-controlled)
- Zakat calculator (Nisab-based, with transaction history)

### FBR / Tax
- POS integration with FBR (Federal Board of Revenue)
- Invoice IRN generation, QR codes, FBR sequence numbers
- FBR resubmission with rate limiting
- HS code support on service catalog

### Inventory & Procurement
- Inventory items with WAC costing and expiry tracking
- Procurement request → approval → GRN workflow
- Automatic stock movements on procurement receipt
- Low-stock and expiry alerts
- Inventory health dashboard

### AI (MedGemma)
- AI-assisted consultation analysis via Ollama (local) or API providers
- Offline queue with retry — analyses queued when Ollama is unavailable
- Cloudflare Tunnel bridge for production Ollama access
- Supports: `medgemma3:4b`, `gemma3:4b`, `llama3.2`, and any Ollama model

### Notifications
- In-app notification centre with unread badge
- SMS via TextBee (Android-bridged SMS gateway) with auto device discovery
- Queue-backed notification delivery

### Reporting
- Financial report with date range filters and department breakdown
- Revenue ledger with per-role attribution
- Staff performance deep-dive (commission, lab orders, top services)
- Procurement pipeline tracker
- Expense intelligence dashboard
- Audit log for financial operations

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Auth & RBAC | Laravel Breeze + Spatie Permission v6 |
| Frontend | Blade, Bootstrap 5, Bootstrap Icons, Vite |
| Database | MySQL (InnoDB, strict mode) |
| PDF Generation | barryvdh/laravel-dompdf |
| HTTP Client | Guzzle 7 |
| API | Laravel Sanctum (REST API for mobile/external) |
| Queue | Laravel Queue (database driver) |
| Cache | Database cache |
| Sessions | Database sessions |
| Mail | SMTP (Gmail / any SMTP) |
| SMS | TextBee (Android SMS gateway) |
| AI | Ollama (local) / OpenAI / Anthropic |
| Error Tracking | Sentry |
| MCP Server | Node.js (Claude AI tool server) |

---

## Roles & Portals

| Role | Portal Prefix | Key Responsibilities |
|------|---------------|----------------------|
| **Owner** | `/owner/` | Financial dashboards, user management, staff contracts, commission configs, expenses, Zakat, FBR settings, procurement approval |
| **Doctor** | `/doctor/` | Patient queue, consultations, notes, prescriptions, lab/radiology order creation, invoice generation |
| **Receptionist** | `/receptionist/` | Patient registration, visit queue, invoicing, payment collection, appointment booking, staff quick-pay |
| **Triage** | `/triage/` | Vitals capture (BP, temp, pulse, SpO2, weight, BMI), patient prioritisation, send-to-doctor |
| **Laboratory** | `/laboratory/` | Test processing, results entry, report generation (PDF), test catalog, equipment management |
| **Radiology** | `/radiology/` | Imaging order processing, image upload, report entry, equipment tracking |
| **Pharmacy** | `/pharmacy/` | Prescription fulfilment, invoice dispensing, inventory stock management |
| **Patient** | `/patient/` | Self-service portal: visit history, invoices, reports |

---

## Architecture Overview

```
HTTP Request
     |
EnsureUserIsActive + Spatie Role Middleware
     |
Role-scoped Routes (owner|doctor|receptionist|...)
     |
Controllers
  |-- FinancialDistributionService  (revenue splits)
  |-- InventoryService              (WAC ledger)
  |-- DoctorPayoutService           (payout approval)
  |-- ProcurementService            (GRN workflow)
  |-- MedGemmaService               (AI analysis)
  |-- FbrService                    (tax compliance)
  |-- ZakatService
  |-- AuditableService              (audit trail)
     |
MySQL Database
  + Queue (jobs / failed_jobs)
  + Cache (cache table)
  + Sessions (sessions table)
```

### Key Design Decisions
- **PHI Encryption**: Patient `phone`, `email`, `cnic`, `consultation_notes` use Laravel's `encrypted` cast — stored as ciphertext in MySQL
- **DB Transactions + Locking**: All financial writes use `DB::transaction()` + `lockForUpdate()` to prevent race conditions
- **Rate Limiting**: AI analysis (10/min), FBR submit (5/min), global search (30/min), notifications poll (10/min)
- **Soft Deletes**: All clinical and financial models use `SoftDeletes`
- **Performance Indexes**: Composite indexes on all foreign keys used in list queries

---

## Quick Start (Local)

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+
- (Optional) Ollama for AI features

### Setup

```bash
# 1. Clone and install dependencies
git clone <repo-url>
cd clinic-system
composer install
npm install && npm run build

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure database in .env
#    DB_DATABASE=clinic_system
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Run migrations and seed
php artisan migrate
php artisan db:seed

# 5. Storage link
php artisan storage:link
```

### Start (Windows — XAMPP)

Double-click **`start.bat`** — it warms caches, starts the queue worker in the background, and launches the dev server on `http://localhost:8000`.

Or manually in separate terminals:

```bash
# Terminal 1 — Web server
php artisan serve

# Terminal 2 — Queue worker
php artisan queue:work --tries=3 --timeout=120

# Terminal 3 — Scheduler (Windows)
# Run start-scheduler.bat
# Linux cron:
# * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Default Accounts

After running `php artisan db:seed`, the following accounts are created:

| Role | Email | Password |
|------|-------|----------|
| Owner | `owner@clinic.com` | `password123` |
| Doctor | `doctor@clinic.com` | `password123` |
| Doctor 2 | `doctor2@clinic.com` | `password123` |
| Receptionist | `receptionist@clinic.com` | `password123` |
| Triage | `triage@clinic.com` | `password123` |
| Laboratory | `lab@clinic.com` | `password123` |
| Radiology | `radiology@clinic.com` | `password123` |
| Pharmacy | `pharmacy@clinic.com` | `password123` |
| Patient | `patient@clinic.com` | `password123` |

> **Never use these credentials in production.** Create users via the Owner portal with strong passwords.

---

## Environment Variables

### Core

```env
APP_NAME="Aviva HealthCare"
APP_ENV=local                  # production in prod
APP_DEBUG=true                 # false in prod
APP_URL=http://localhost        # https://your-domain.com in prod
APP_TIMEZONE=Asia/Karachi
```

### Database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=clinic_system
DB_USERNAME=root
DB_PASSWORD=
```

### Mail (Gmail SMTP)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your-app-password   # 16-char Gmail App Password
MAIL_FROM_ADDRESS="your@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> Use a **Gmail App Password** (not your account password). Generate one at: Google Account > Security > 2-Step Verification > App Passwords.

### SMS — TextBee

```env
TEXTBEE_API_KEY=your-textbee-api-key
TEXTBEE_DEVICE_ID=             # Leave empty — auto-discovered from registered device
```

### AI — MedGemma / Ollama

```env
MEDGEMMA_PROVIDER=ollama        # ollama | openai | anthropic
MEDGEMMA_MODEL=medgemma3:4b
MEDGEMMA_API_URL=http://127.0.0.1:11434   # Local Ollama
# In production with Cloudflare Tunnel:
# MEDGEMMA_API_URL=https://YOUR-TUNNEL.trycloudflare.com
MEDGEMMA_TIMEOUT=120
```

### FBR (Pakistan Tax)

```env
FBR_NTN=
FBR_STRN=
FBR_POS_ID=
FBR_CASHIER_ID=
```

### Error Tracking

```env
SENTRY_LARAVEL_DSN=            # From sentry.io > project settings > SDK setup
```

See `.env.production.example` for the full production-ready template.

---

## Production Deployment

```bash
bash scripts/deploy.sh
```

The script performs:
1. `git pull --ff-only`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. Config, route, view, event cache rebuild
6. Queue restart

### Supervisor (Linux — keep queue worker alive)

A ready-made supervisor config is at `scripts/supervisor.conf`:

```bash
sudo cp scripts/supervisor.conf /etc/supervisor/conf.d/aviva.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start aviva-worker:*
```

### Required Production `.env` Changes

```env
APP_ENV=production
APP_DEBUG=false
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

---

## AI Integration (MedGemma / Ollama)

The system uses **Ollama** to run AI models locally for consultation analysis:

```bash
# Install Ollama from https://ollama.com
ollama pull medgemma3:4b   # or: gemma3:4b, llama3.2, phi4-mini

# Start Ollama (runs on port 11434 by default)
ollama serve
```

**Offline resilience**: If Ollama is unavailable, analyses are queued as `AiAnalysis` records with `status=pending`. The `RetryOfflineAnalyses` artisan command retries them:

```bash
php artisan ai:retry-offline
# Or let the scheduler run it automatically every 5 minutes
```

**Production (Cloudflare Tunnel)**: Run `scripts/start-ollama-tunnel.bat` on the machine hosting Ollama. It prints a public HTTPS URL — set that as `MEDGEMMA_API_URL` in production `.env`.

---

## SMS Notifications (TextBee)

TextBee uses an Android phone as an SMS gateway — no SIM card fees, no Twilio account needed.

**Setup:**
1. Install the **TextBee app** on an Android phone
2. Register at [app.textbee.dev](https://app.textbee.dev) and log in with your API key
3. The app auto-registers your device — the system discovers it automatically via the API
4. Set `TEXTBEE_API_KEY` in `.env` — `TEXTBEE_DEVICE_ID` is optional (auto-discovered and cached for 24 h)

SMS notifications are sent for appointment reminders and patient-facing events.

---

## FBR Tax Compliance

The system supports Pakistan's FBR Point-of-Sale integration:

- Each paid invoice generates an FBR invoice number and IRN
- QR codes are embedded in PDF receipts
- FBR POS sequence maintained per session
- Failed FBR submissions can be resubmitted from the receptionist portal (rate-limited to 5/min)
- HS codes configurable per service catalog item

Configure at: Owner > Platform Settings > FBR Settings.

---

## Financial Architecture

### Revenue Distribution
When an invoice is marked paid, `FinancialDistributionService` splits the revenue:
- Doctor commission (per `CommissionConfig` rules)
- Department overhead
- All entries written to `revenue_ledgers` table

### Doctor Payouts
- Owner creates a payout referencing unpaid `revenue_ledger` entries
- Payout goes through an approval workflow
- On confirmation, `payout_id` is stamped on ledger entries and marked settled

### WAC Inventory
`InventoryService` maintains Weighted Average Cost per item:
- Every stock receipt recalculates WAC
- Every dispensing deducts at current WAC
- Full movement history in `stock_movements` table

---

## Queue Workers & Scheduler

### Jobs
| Job | Trigger | Description |
|-----|---------|-------------|
| `AnalyseConsultationJob` | Invoice marked complete | Sends consultation notes to MedGemma for AI analysis |
| `SendSmsNotificationJob` | Appointment booked | Sends SMS via TextBee |

### Scheduled Commands
| Command | Frequency | Description |
|---------|-----------|-------------|
| `ai:retry-offline` | Every 5 min | Retries pending AI analyses when Ollama comes back online |
| `cleanup:radiology-images` | Daily | Purges orphaned radiology image files (chunked, memory-safe) |
| `queue:prune-failed` | Daily | Cleans failed job records older than 7 days |

---

## MCP Server (Claude AI Integration)

The project includes a **Model Context Protocol (MCP) server** that gives Claude AI real-time access to clinic data:

```bash
# Location: mcp/server.js
node mcp/server.js
```

**Available tools:**

| Tool | Description |
|------|-------------|
| `get_clinic_stats` | Live patient count, today's revenue, pending invoices |
| `get_pending_items` | Pending lab/radiology work, procurement requests needing approval |
| `check_ai_status` | MedGemma/Ollama availability and pending analysis queue |

Configure in Claude Code settings (`~/.claude/settings.json`) under the `clinic` MCP server entry.

---

## Security

- **PHI Encryption**: Patient PII (`phone`, `email`, `cnic`, `consultation_notes`) encrypted at rest using Laravel's `encrypted` cast (AES-256-CBC via `APP_KEY`)
- **RBAC**: Spatie Permission — every route group protected by role middleware; cross-role access returns 403
- **Session Security**: Database-backed sessions; `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true` in production
- **CSRF**: All forms and state-changing AJAX calls require a CSRF token
- **Rate Limiting**: Per-endpoint rate limiters prevent abuse of AI, FBR, search, and notification endpoints
- **`EnsureUserIsActive` Middleware**: Inactive users (`is_active = false`) are logged out immediately on any request
- **Audit Log**: Financial operations (expense deletes, payout confirms, etc.) written to `audit_logs` via `AuditableService`
- **DB Transactions + Row Locks**: All financial writes use `lockForUpdate()` to prevent double-spend race conditions

---

## Project Structure

```
clinic-system/
├── app/
│   ├── Channels/          TextBee SMS channel
│   ├── Console/Commands/  Artisan commands (AI retry, image cleanup)
│   ├── Http/
│   │   ├── Controllers/   Role-namespaced controllers (Owner/, Doctor/, etc.)
│   │   └── Middleware/    EnsureUserIsActive, role enforcement
│   ├── Jobs/              Queue jobs (AI analysis, SMS)
│   ├── Models/            Eloquent models with encrypted casts
│   ├── Notifications/     In-app + SMS notifications
│   └── Services/          Business logic layer
│       ├── FinancialDistributionService.php
│       ├── InventoryService.php (WAC)
│       ├── DoctorPayoutService.php
│       ├── MedGemmaService.php
│       ├── FbrService.php
│       ├── ZakatService.php
│       └── AuditableService.php
├── database/
│   ├── migrations/        Timestamped migrations
│   └── seeders/           Role seeder, user seeder, lab stock seeder
├── mcp/
│   └── server.js          Claude MCP tool server
├── resources/views/       Blade templates per role
├── routes/
│   ├── web.php            Root redirect + auth routes
│   ├── owner.php          Owner portal routes
│   ├── doctor.php         Doctor portal routes
│   ├── receptionist.php   Receptionist portal routes
│   ├── triage.php         Triage portal routes
│   ├── laboratory.php     Lab portal routes
│   ├── radiology.php      Radiology portal routes
│   ├── pharmacy.php       Pharmacy portal routes
│   ├── patient.php        Patient self-service routes
│   ├── shared.php         Cross-role routes (notifications, profile, search)
│   └── api.php            REST API (Sanctum-protected)
├── scripts/
│   ├── deploy.sh          Production deploy script
│   ├── supervisor.conf    Supervisor config for queue workers
│   └── start-ollama-tunnel.bat  Cloudflare Tunnel for Ollama
├── start.bat              Windows quick-start (server + queue)
├── start-scheduler.bat    Windows scheduler loop
└── .env.production.example  Production environment template
```

---

## License

Private — Aviva HealthCare. All rights reserved.
