<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class ReEncryptPhiFields extends Command
{
    protected $signature = 'phi:re-encrypt {--dry-run : Show what would be updated without writing}';

    protected $description = 'Re-encrypt PHI fields that were seeded as plaintext before encrypted casts were added';

    private int $updated = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        if ($dry) {
            $this->warn('DRY RUN — no writes will occur');
        }

        $this->processTable('patients', ['phone', 'email', 'cnic', 'consultation_notes'], $dry);
        $this->processTable('visits', ['consultation_notes'], $dry);
        $this->processTable('triage_vitals', ['chief_complaint', 'notes'], $dry);
        $this->processTable('prescriptions', ['diagnosis', 'notes'], $dry);

        $this->info("Done. Updated: {$this->updated} | Already encrypted / null: {$this->skipped}");

        return self::SUCCESS;
    }

    private function processTable(string $table, array $columns, bool $dry): void
    {
        $rows = DB::table($table)->select(array_merge(['id'], $columns))->get();

        foreach ($rows as $row) {
            $updates = [];

            foreach ($columns as $col) {
                $raw = $row->{$col};

                if ($raw === null || $this->isAlreadyEncrypted($raw)) {
                    $this->skipped++;
                    continue;
                }

                $updates[$col] = Crypt::encrypt($raw);
            }

            if (empty($updates)) {
                continue;
            }

            $this->line("  [{$table}#{$row->id}] encrypting: " . implode(', ', array_keys($updates)));

            if (! $dry) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }

            $this->updated += count($updates);
        }

        $this->info("  {$table}: done");
    }

    private function isAlreadyEncrypted(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $decoded = base64_decode($value, strict: true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
