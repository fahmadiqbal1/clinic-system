<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up radiology images older than 30 days — runs daily at 2 AM
Schedule::command('radiology:cleanup-images')->dailyAt('02:00');

// Check for stock batches expiring within 30 days — runs daily at 7 AM
Schedule::command('inventory:check-expiry')->dailyAt('07:00');

// Retry AI analyses queued while Ollama was offline — runs every 10 minutes
Schedule::command('ai:retry-pending')->everyTenMinutes();

// Send 24-hour appointment reminders to patients — runs hourly
Schedule::command('appointments:send-reminders')->hourly();

// Sync service catalog + inventory to RAGFlow corpus — nightly at 03:00 (Phase 3)
Schedule::command('ragflow:sync')->dailyAt('03:00');

// Flag inventory procurement receipts overdue >48 hours — every 2 hours
Schedule::command('procurement:check-receipts')->everyTwoHours();

// Daily ops AI monitor: auto-draft critical stock, flag warning stock — 06:30
Schedule::command('ai:ops-procurement-monitor')->dailyAt('06:30');

// Soft-archive inventory items out of stock for 12+ months — every Sunday at 02:00
Schedule::command('inventory:archive-stale')->weekly()->sundays()->at('02:00');
