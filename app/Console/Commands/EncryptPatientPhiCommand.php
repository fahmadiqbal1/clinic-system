<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-shot command: detect plain-text encrypted-cast fields across all PHI tables
 * and re-save them through Crypt so they're properly encrypted.
 *
 * Usage:  php artisan patients:encrypt-phi [--dry-run]
 */
class EncryptPatientPhiCommand extends Command
{
    protected $signature   = 'patients:encrypt-phi {--dry-run : Report counts without writing}';
    protected $description = 'Re-encrypt all plain-text PHI fields across patients, prescriptions, triage_vitals, and visits';

    private const TABLES = [
        'patients'      => ['phone', 'email', 'cnic', 'consultation_notes'],
        'prescriptions' => ['diagnosis', 'notes'],
        'triage_vitals' => ['chief_complaint', 'notes'],
        'visits'        => ['consultation_notes'],
        'platform_settings' => ['api_key'],
        'ai_analyses'   => ['prompt_summary', 'ai_response'],
    ];

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $grandTotal   = 0;
        $grandUpdated = 0;

        foreach (self::TABLES as $table => $cols) {
            [$total, $updated] = $this->processTable($table, $cols, $dryRun);
            $grandTotal   += $total;
            $grandUpdated += $updated;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("Done. {$verb} {$grandUpdated} / {$grandTotal} rows across " . count(self::TABLES) . " tables.");

        return self::SUCCESS;
    }

    private function processTable(string $table, array $cols, bool $dryRun): array
    {
        $total   = 0;
        $updated = 0;

        DB::table($table)->orderBy('id')->chunk(200, function ($rows) use ($table, $cols, $dryRun, &$total, &$updated) {
            foreach ($rows as $row) {
                $total++;
                $dirty = [];

                foreach ($cols as $col) {
                    $raw = $row->{$col} ?? null;
                    if ($raw === null || $raw === '') {
                        continue;
                    }

                    if ($this->isPlainText($raw)) {
                        $dirty[$col] = Crypt::encryptString($raw);
                    }
                }

                if (empty($dirty)) {
                    continue;
                }

                $updated++;

                if (! $dryRun) {
                    DB::table($table)->where('id', $row->id)->update($dirty);
                }
            }
        });

        $this->line("  {$table}: checked {$total}, " . ($dryRun ? 'would update' : 'updated') . " {$updated}");
        return [$total, $updated];
    }

    private function isPlainText(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return false;
        } catch (\Throwable) {
            return true;
        }
    }
}
