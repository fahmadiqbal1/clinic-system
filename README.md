<div align="center">

# 🏥 Aviva HealthCare

### A Full-Featured Multi-Role Clinic ERP System

*Built for Pakistani Private Clinics · FBR Tax Compliant · AI-Assisted Diagnostics*

<br>

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.x-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-Private-6B7280?style=flat-square)](#license)

<br>

> A complete clinic management system covering patient care, financial distribution, FBR tax compliance, AI-powered diagnostics, and multi-role staff portals — all in a single Laravel application.

<br>

[Quick Start](#-quick-start) · [Roles & Portals](#-roles--portals) · [Architecture](#-architecture) · [AI Integration](#-ai-integration-medgemma--ollama) · [Financial Model](#-financial-architecture)

</div>

---

<br>

## ✨ Feature Domains

<br>

### Clinical

| Feature | Details |
|---|---|
| Patient Registration | MR number · CNIC (encrypted) · doctor assignment |
| Visit Queue & Triage | BP · Temp · Pulse · SpO2 · Weight · BMI |
| Consultation Workspace | Doctor notes · prescriptions · lab/radiology orders |
| Appointment Scheduling | Conflict detection + patient check-in kiosk support |

<br>

### Financial

| Feature | Details |
|---|---|
| Multi-department Invoicing | Consultation · Laboratory · Radiology · Pharmacy |
| Revenue Distribution | Splits each payment across doctor commission, lab fee, overhead |
| WAC Inventory Ledger | Weighted Average Cost per stock item |
| Doctor Payout Workflow | Approval-gated payout referencing unpaid ledger entries |
| Staff Contract Types | Salary · Commission · Hybrid |
| Expense Tracking | Manual + procurement-linked |
| Department P&L | Per-department revenue and cost dashboards |
| Zakat Calculator | Nisab-based with full transaction history |
| Discount Approval | Owner-controlled discount workflow |

<br>

### Compliance & Tax

| Feature | Details |
|---|---|
| FBR POS Integration | IRN generation · QR codes · FBR sequence numbers |
| HS Code Support | Per-service catalog item |
| FBR Resubmission | Rate-limited resubmit for failed submissions |

<br>

### AI, Notifications & Reporting

| Feature | Details |
|---|---|
| MedGemma Diagnostics | AI consultation analysis via local Ollama or cloud API |
| Offline Queue | Analyses queued when Ollama unavailable, auto-retried |
| SMS Notifications | TextBee Android gateway — appointment reminders and alerts |
| In-App Notifications | Notification centre with unread badge |
| Financial Reports | Date-range, department breakdown, per-role attribution |
| Audit Log | All financial operations logged via `AuditableService` |

<br>

---

<br>

## 🏗️ Architecture

<br>

### Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel 12 · PHP 8.2+ |
| **Auth & RBAC** | Laravel Breeze + Spatie Permission v6 |
| **Frontend** | Blade · Bootstrap 5 · Bootstrap Icons · Vite |
| **Database** | MySQL 8.0+ (InnoDB, strict mode) |
| **Queue** | Laravel Queue (database driver) |
| **AI** | Ollama (local) · OpenAI · Anthropic |
| **SMS** | TextBee (Android SMS gateway) |
| **PDF** | barryvdh/laravel-dompdf |
| **Error Tracking** | Sentry |
| **MCP Server** | Node.js (Claude AI tool server) |

<br>

### Request Flow

```
HTTP Request
     │
     ▼
EnsureUserIsActive + Spatie Role Middleware
     │
     ▼
Role-Scoped Routes
(owner │ doctor │ receptionist │ triage │ lab │ radiology │ pharmacy │ patient)
     │
     ▼
Controllers
  ├── FinancialDistributionService   revenue splits on payment
  ├── InventoryService               WAC ledger on stock movement
  ├── DoctorPayoutService            payout approval workflow
  ├── ProcurementService             request → approval → GRN
  ├── MedGemmaService                AI consultation analysis
  ├── FbrService                     FBR POS tax submission
  ├── ZakatService                   Nisab-based calculator
  └── AuditableService               audit trail for financial ops
     │
     ▼
MySQL Database
  + Queue (jobs / failed_jobs)
  + Cache + Sessions (database-backed)
```

<br>

### Key Design Decisions

- **PHI Encryption** — `phone`, `email`, `cnic`, `consultation_notes` use Laravel's `encrypted` cast (AES-256-CBC via `APP_KEY`)
- **DB Transactions + Row Locks** — all financial writes use `DB::transaction()` + `lockForUpdate()` to prevent race conditions
- **Rate Limiting** — AI analysis (10/min) · FBR submit (5/min) · global search (30/min)
- **Soft Deletes** — all clinical and financial models use `SoftDeletes`
- **Composite Indexes** — on all foreign keys used in list queries

<br>

---

<br>

## 👥 Roles & Portals

<br>

| Role | Prefix | Key Responsibilities |
|---|---|---|
| **Owner** | `/owner/` | Financial dashboards · user management · staff contracts · commission configs · expenses · Zakat · FBR settings · procurement approval |
| **Doctor** | `/doctor/` | Patient queue · consultations · notes · prescriptions · lab/radiology orders · invoice generation |
| **Receptionist** | `/receptionist/` | Patient registration · visit queue · invoicing · payment collection · appointment booking |
| **Triage** | `/triage/` | Vitals capture (BP · Temp · Pulse · SpO2 · BMI) · patient prioritisation |
| **Laboratory** | `/laboratory/` | Test processing · results entry · PDF report generation · test catalog · equipment management |
| **Radiology** | `/radiology/` | Imaging orders · image upload · report entry · equipment tracking |
| **Pharmacy** | `/pharmacy/` | Prescription fulfilment · invoice dispensing · inventory stock management |
| **Patient** | `/patient/` | Visit history · invoices · reports (self-service portal) |

<br>

---

<br>

## 🚀 Quick Start

<br>

### Prerequisites

- PHP **8.2+** with standard Laravel extensions
- Composer **2.x** · Node.js **18+** · MySQL **8.0+**
- Ollama *(optional — for AI features)*

<br>

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

# 4. Run migrations and seeders
php artisan migrate
php artisan db:seed

# 5. Storage link
php artisan storage:link
```

<br>

### Start (Windows — XAMPP)

Double-click **`start.bat`** — warms caches, starts the queue worker, and launches the dev server at `http://localhost:8000`.

Or run manually in separate terminals:

```bash
# Terminal 1 — Web server
php artisan serve

# Terminal 2 — Queue worker
php artisan queue:work --tries=3 --timeout=120

# Terminal 3 — Scheduler (Windows: run start-scheduler.bat)
# Linux cron: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

<br>

---

<br>

## 🔑 Default Credentials

> **Never use these in production.** Create users via the Owner portal with strong passwords.

| Role | Email | Password |
|---|---|---|
| Owner | `owner@clinic.com` | `password123` |
| Doctor | `doctor@clinic.com` | `password123` |
| Doctor 2 | `doctor2@clinic.com` | `password123` |
| Receptionist | `receptionist@clinic.com` | `password123` |
| Triage | `triage@clinic.com` | `password123` |
| Laboratory | `lab@clinic.com` | `password123` |
| Radiology | `radiology@clinic.com` | `password123` |
| Pharmacy | `pharmacy@clinic.com` | `password123` |
| Patient | `patient@clinic.com` | `password123` |

<br>

---

<br>

## ⚙️ Environment Variables

<br>

### Core

```env
APP_NAME="Aviva HealthCare"
APP_ENV=local          # production in prod
APP_DEBUG=true         # false in prod
APP_URL=http://localhost
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

### Mail *(Gmail SMTP)*

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your-app-password    # 16-char Gmail App Password
MAIL_FROM_ADDRESS="your@gmail.com"
```

> Generate a Gmail App Password at: **Google Account → Security → 2-Step Verification → App Passwords**

### SMS — TextBee

```env
TEXTBEE_API_KEY=your-textbee-api-key
TEXTBEE_DEVICE_ID=     # Leave empty — auto-discovered from registered device
```

### AI — MedGemma / Ollama

```env
MEDGEMMA_PROVIDER=ollama           # ollama | openai | anthropic
MEDGEMMA_MODEL=medgemma3:4b
MEDGEMMA_API_URL=http://127.0.0.1:11434
# Production with Cloudflare Tunnel:
# MEDGEMMA_API_URL=https://YOUR-TUNNEL.trycloudflare.com
MEDGEMMA_TIMEOUT=120
```

### FBR *(Pakistan Tax)*

```env
FBR_NTN=
FBR_STRN=
FBR_POS_ID=
FBR_CASHIER_ID=
```

<br>

---

<br>

## 🤖 AI Integration (MedGemma / Ollama)

<br>

```bash
# Install Ollama from https://ollama.com
ollama pull medgemma3:4b   # or: gemma3:4b, llama3.2, phi4-mini
ollama serve
```

### Offline Resilience

```
Consultation saved
      │
      ▼
Ollama available? ──No──► Queue as AiAnalysis (status=pending)
      │                           │
     Yes                   Scheduler retries every 5 min
      │                    (php artisan ai:retry-offline)
      ▼                           │
AI Analysis result ◄──────────────┘
      │
      ▼
Stored on consultation record
```

**Production:** Run `scripts/start-ollama-tunnel.bat` — it creates a Cloudflare Tunnel and prints the public HTTPS URL to set as `MEDGEMMA_API_URL`.

<br>

---

<br>

## 📱 SMS Notifications (TextBee)

TextBee routes SMS through an Android phone — no SIM fees, no Twilio account needed.

**Setup:**
1. Install the **TextBee** app on an Android phone
2. Register at [app.textbee.dev](https://app.textbee.dev) with your API key
3. The device auto-registers — `TEXTBEE_DEVICE_ID` is discovered and cached for 24 h
4. Set `TEXTBEE_API_KEY` in `.env`

<br>

---

<br>

## 🏦 Financial Architecture

<br>

### Revenue Distribution

When an invoice is marked paid, `FinancialDistributionService` splits revenue atomically:

```
Invoice Paid
      │
      ▼
FinancialDistributionService (inside DB::transaction + lockForUpdate)
  ├── Doctor commission    ← per CommissionConfig rules
  ├── Department overhead
  └── All entries → revenue_ledgers table
```

### Doctor Payouts

```
Owner creates payout → references unpaid revenue_ledger entries
      │
      ▼
Approval workflow
      │
      ▼
On confirm: payout_id stamped on ledger entries → status = settled
```

### WAC Inventory

Every stock receipt recalculates Weighted Average Cost. Every dispensing deducts at current WAC. Full movement history in `stock_movements`.

<br>

---

<br>

## ⏱️ Queue Workers & Scheduler

<br>

### Jobs

| Job | Trigger | Description |
|---|---|---|
| `AnalyseConsultationJob` | Invoice marked complete | Sends notes to MedGemma for AI analysis |
| `SendSmsNotificationJob` | Appointment booked | Sends SMS via TextBee |

### Scheduled Commands

| Command | Frequency | Description |
|---|---|---|
| `ai:retry-offline` | Every 5 min | Retries pending AI analyses |
| `cleanup:radiology-images` | Daily | Purges orphaned radiology image files |
| `queue:prune-failed` | Daily | Cleans failed jobs older than 7 days |

<br>

---

<br>

## 🔌 MCP Server (Claude AI Integration)

The project includes a **Model Context Protocol server** giving Claude AI real-time access to clinic data:

```bash
node mcp/server.js
```

| Tool | Description |
|---|---|
| `get_clinic_stats` | Live patient count · today's revenue · pending invoices |
| `get_pending_items` | Pending lab/radiology work · procurement requests |
| `check_ai_status` | MedGemma availability and pending analysis queue |

<br>

---

<br>

## 🔒 Security

| Area | Implementation |
|---|---|
| **PHI Encryption** | AES-256-CBC via `encrypted` cast on all patient PII |
| **RBAC** | Spatie Permission — role middleware on every route group |
| **Session Security** | `SESSION_ENCRYPT=true` · `SESSION_SECURE_COOKIE=true` |
| **CSRF** | All forms and state-changing AJAX require CSRF token |
| **Rate Limiting** | Per-endpoint limiters on AI, FBR, search, and notifications |
| **Active Check** | `EnsureUserIsActive` middleware — inactive users logged out immediately |
| **Audit Log** | Financial operations written to `audit_logs` via `AuditableService` |
| **DB Locking** | `lockForUpdate()` on all financial writes — prevents double-spend |

<br>

---

<br>

## 📁 Project Structure

```
clinic-system/
├── app/
│   ├── Channels/              TextBee SMS channel
│   ├── Console/Commands/      Artisan commands (AI retry, image cleanup)
│   ├── Http/
│   │   ├── Controllers/       Role-namespaced (Owner/ Doctor/ Receptionist/ …)
│   │   └── Middleware/        EnsureUserIsActive, role enforcement
│   ├── Jobs/                  Queue jobs (AI analysis, SMS)
│   ├── Models/                Eloquent models with encrypted casts
│   ├── Notifications/         In-app + SMS notifications
│   └── Services/              Business logic layer
│       ├── FinancialDistributionService.php
│       ├── InventoryService.php
│       ├── DoctorPayoutService.php
│       ├── MedGemmaService.php
│       ├── FbrService.php
│       ├── ZakatService.php
│       └── AuditableService.php
├── database/
│   ├── migrations/
│   └── seeders/
├── mcp/
│   └── server.js              Claude MCP tool server
├── resources/views/           Blade templates per role
├── routes/
│   ├── owner.php · doctor.php · receptionist.php
│   ├── triage.php · laboratory.php · radiology.php
│   ├── pharmacy.php · patient.php · shared.php · api.php
├── scripts/
│   ├── deploy.sh              Production deploy script
│   ├── supervisor.conf        Queue worker supervisor config
│   └── start-ollama-tunnel.bat
├── start.bat                  Windows quick-start
└── .env.production.example    Production environment template
```

<br>

---

<br>

## 🚢 Production Deployment

```bash
bash scripts/deploy.sh
```

Steps performed automatically:

1. `git pull --ff-only`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. Config · route · view · event cache rebuild
6. Queue restart

**Supervisor** *(keep queue worker alive)*:

```bash
sudo cp scripts/supervisor.conf /etc/supervisor/conf.d/aviva.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start aviva-worker:*
```

**Required production `.env` changes:**

```env
APP_ENV=production
APP_DEBUG=false
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

<br>

---

<br>

<div align="center">

**Aviva HealthCare** · Built for Private clinics

*Laravel 12 · PHP 8.2+ · Bootstrap 5 · MedGemma AI*

</div>
---

## License

Private — Aviva HealthCare. All rights reserved.
