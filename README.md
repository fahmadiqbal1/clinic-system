# Aviva HealthCare — Clinic ERP

A full-featured clinic management system built with Laravel, Blade, and Vite.

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
# Install dependencies
composer install
npm install && npm run build

# Environment
cp .env.example .env
php artisan key:generate

# Database (MySQL)
php artisan migrate:fresh --seed
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
