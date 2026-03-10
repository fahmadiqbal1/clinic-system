# Aviva HealthCare — Clinic ERP

A Laravel-based clinic management system with PHI encryption, AI-assisted diagnostics (MedGemma/Gemini), patient portal, triage, lab, prescriptions, and FBR IRIS digital invoicing.

> ⚠️ **Security Notice — PHI System**
> This system handles Protected Health Information (PHI). The production `.env` file must **never** be committed to version control. Any credentials that were ever committed to the repository's git history must be rotated immediately. See [SECURITY.md](SECURITY.md) for vulnerability reporting.

## Roles

| Role | Portal |
|------|--------|
| Owner | Financial dashboards, staff contracts, commission configs, zakat, expenses |
| Doctor | Consultations, prescriptions, lab/radiology orders, payouts |
| Receptionist | Patient registration, invoicing, visit queue |
| Triage | Vitals capture, patient prioritisation |
| Laboratory | Test processing, inventory, procurement |
| Radiology | Imaging orders, equipment tracking |
| Pharmacy | Dispensing, prescription fulfilment, stock management |

## Quick Start

```bash
# Clone the repository
git clone https://github.com/fahmadiqbal1/clinic-system.git
cd clinic-system

# Install dependencies
composer install
npm install && npm run build

# Environment
cp .env.example .env
php artisan key:generate

# Configure .env with your DB credentials, API keys, and mail settings

# Database (MySQL)
php artisan migrate
php artisan db:seed --class=LaboratoryStockSeeder

# Serve
php artisan serve
```

## Default Accounts

Default accounts are created by the seeder. Credentials are printed to the console during seeding.

**For development environments only**, the seeder uses:
- Email format: `{role}@clinic.com` (e.g., `owner@clinic.com`, `doctor@clinic.com`)
- Password: Set via `SEED_DEFAULT_PASSWORD` environment variable, or randomly generated

> ⚠️ **Security Note:** Never use default credentials in production. Run fresh seeders with secure passwords or create users through the Owner portal.

## Stack

- **Backend:** Laravel 12, Spatie Permission
- **Frontend:** Blade, Vite, Tailwind CSS
- **Database:** MySQL
- **Mail:** SMTP (Gmail)
