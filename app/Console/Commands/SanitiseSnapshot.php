<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Faker\Factory as Faker;

class SanitiseSnapshot extends Command
{
    protected $signature = 'fixtures:sanitise-snapshot
                            {--output=tests/fixtures/sanitised-snapshot.sql : Output SQL path (relative to base_path)}
                            {--encrypt : Encrypt the output file with openssl (requires FIXTURE_ENCRYPTION_KEY env var)}';

    protected $description = 'Replace all PHI in a copy of the current DB with Faker data. Produces a safe dev/test fixture. No raw production data is written; schema and row shapes are preserved.';

    public function handle(): int
    {
        $this->info('Starting PHI sanitisation...');

        $outPath = base_path($this->option('output'));
        $dir = dirname($outPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $faker = Faker::create('en_US');
        $sanitised = 0;

        DB::transaction(function () use ($faker, &$sanitised) {
            // Sanitise patients — all PHI fields
            Patient::withTrashed()->chunk(500, function ($patients) use ($faker, &$sanitised) {
                foreach ($patients as $patient) {
                    $patient->forceFill([
                        'first_name'         => $faker->firstName,
                        'last_name'          => $faker->lastName,
                        'phone'              => $faker->numerify('03#########'),
                        'email'              => $faker->unique()->safeEmail,
                        'cnic'              => $faker->numerify('#####-#######-#'),
                        'consultation_notes' => $patient->consultation_notes
                            ? '[SANITISED] ' . $faker->sentence(10)
                            : null,
                    ])->saveQuietly();
                    $sanitised++;
                }
            });

            // Sanitise users — name and email (not owner@clinic.com seeded accounts)
            User::chunk(100, function ($users) use ($faker) {
                foreach ($users as $user) {
                    if (in_array($user->email, $this->seedAccounts())) {
                        continue;
                    }
                    $user->forceFill([
                        'name'  => $faker->name,
                        'email' => $faker->unique()->safeEmail,
                    ])->saveQuietly();
                }
            });
        });

        $this->info("Sanitised {$sanitised} patient records.");
        $this->info('Dumping sanitised database...');

        // Dump the staging DB (must be run against clinic_system_staging, not production)
        $dbName   = config('database.connections.mysql.database');
        $dbHost   = config('database.connections.mysql.host');
        $dbPort   = config('database.connections.mysql.port');
        $dbUser   = config('database.connections.mysql.username');
        $dbPass   = config('database.connections.mysql.password');

        if ($dbName === 'clinic_system') {
            $this->error('ABORT: this command must NOT run against the production database (clinic_system).');
            $this->error('Point DB_DATABASE to clinic_system_staging or a dedicated fixture database.');
            return self::FAILURE;
        }

        $mysqldump = PHP_OS_FAMILY === 'Windows'
            ? 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe'
            : 'mysqldump';

        // Write a temporary MySQL option file to avoid shell quoting issues with passwords
        $optFile = tempnam(sys_get_temp_dir(), 'mysql_opts_');
        file_put_contents($optFile, "[client]\npassword={$dbPass}\n");

        $args = [
            escapeshellarg($mysqldump),
            "--defaults-extra-file=" . escapeshellarg($optFile),
            "-h", escapeshellarg($dbHost),
            "-P", (int) $dbPort,
            "-u", escapeshellarg($dbUser),
            "--no-tablespaces",
            "--single-transaction",
            escapeshellarg($dbName),
        ];

        $cmd = implode(' ', $args);
        $descriptors = [1 => ['file', $outPath, 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        $exitCode = $proc ? proc_close($proc) : 1;
        @unlink($optFile);

        if (! file_exists($outPath) || filesize($outPath) < 1024) {
            $this->error("Dump failed or file too small. Check DB credentials and mysqldump path.");
            return self::FAILURE;
        }

        $sizeKb = round(filesize($outPath) / 1024);
        $this->info("Dump written: {$outPath} ({$sizeKb} KB)");

        if ($this->option('encrypt')) {
            $key = env('FIXTURE_ENCRYPTION_KEY');
            if (! $key) {
                $this->warn('FIXTURE_ENCRYPTION_KEY not set — skipping encryption. Set it and re-run with --encrypt.');
                return self::SUCCESS;
            }
            $encPath = $outPath . '.enc';
            exec("openssl enc -aes-256-cbc -pbkdf2 -k " . escapeshellarg($key)
                . " -in " . escapeshellarg($outPath)
                . " -out " . escapeshellarg($encPath), $encOut, $encExit);
            if ($encExit === 0) {
                unlink($outPath);
                $this->info("Encrypted fixture: {$encPath}");
                $this->info('Add FIXTURE_ENCRYPTION_KEY to .env.staging. Never commit the plaintext .sql.');
            } else {
                $this->warn('openssl encryption failed. Plaintext SQL left at ' . $outPath);
            }
        }

        $this->info('Done. Verify no real names/emails appear before committing.');
        return self::SUCCESS;
    }

    private function seedAccounts(): array
    {
        return [
            'owner@clinic.com', 'doctor@clinic.com', 'receptionist@clinic.com',
            'triage@clinic.com', 'lab@clinic.com', 'radiology@clinic.com',
            'pharmacy@clinic.com', 'patient@clinic.com',
        ];
    }
}
