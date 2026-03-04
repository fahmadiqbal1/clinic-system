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

| Email | Password | Role |
|-------|----------|------|
| owner@clinic.com | password123 | Owner |
| doctor@clinic.com | password123 | Doctor |
| doctor2@clinic.com | password123 | Doctor |
| receptionist@clinic.com | password123 | Receptionist |
| triage@clinic.com | password123 | Triage |
| lab@clinic.com | password123 | Laboratory |
| radiology@clinic.com | password123 | Radiology |
| pharmacy@clinic.com | password123 | Pharmacy |

## Stack

- **Backend:** Laravel 11, Spatie Permission
- **Frontend:** Blade, Vite, Tailwind CSS
- **Database:** MySQL
- **Mail:** SMTP (Gmail)
