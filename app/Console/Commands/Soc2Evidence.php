<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class Soc2Evidence extends Command
{
    protected $signature = 'soc2:evidence
                            {--from= : Start date YYYY-MM-DD inclusive (omit for all history)}
                            {--to=   : End date YYYY-MM-DD inclusive (default: today)}';

    protected $description = 'Export SOC 2 evidence bundle: audit chain, AI invocations, chain-verify proof, feature-flag snapshot';

    public function handle(): int
    {
        [$from, $to] = $this->parseDates();
        if ($from === false) {
            return self::FAILURE;
        }

        $label = $from ? "{$from} → {$to}" : "all → {$to}";
        $this->info("Generating SOC 2 evidence bundle ({$label})…");

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'soc2_' . time();
        mkdir($tmpDir, 0750, true);

        try {
            $counts = $this->exportAuditLogs($tmpDir, $from, $to);
            $counts += $this->exportAiInvocations($tmpDir, $from, $to);
            $this->exportChainProof($tmpDir);
            $flagCount = $this->exportFeatureFlags($tmpDir);
            $this->exportManifest($tmpDir, $from, $to, $counts, $flagCount);

            $zipPath = $this->createZip($tmpDir, $from, $to);
        } finally {
            $this->deleteDir($tmpDir);
        }

        $this->info("Bundle: {$zipPath}");
        return self::SUCCESS;
    }

    private function parseDates(): array
    {
        $from = $this->option('from') ?: null;
        $to   = $this->option('to')   ?: now()->toDateString();

        $dateRe = '/^\d{4}-\d{2}-\d{2}$/';

        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            if ($value !== null && !preg_match($dateRe, $value)) {
                $this->error("--{$name} must be YYYY-MM-DD, got: {$value}");
                return [false, false];
            }
        }

        if ($from !== null && $from > $to) {
            $this->error('--from must not be later than --to.');
            return [false, false];
        }

        return [$from, $to];
    }

    private function exportAuditLogs(string $dir, ?string $from, string $to): array
    {
        $query = DB::table('audit_logs');
        if ($from) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        $query->where('created_at', '<=', $to . ' 23:59:59');

        $rows = $query->orderBy('id')->get()->map(fn ($r) => (array) $r);

        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'audit_logs.json',
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $count = $rows->count();
        $this->line("  audit_logs       : {$count} rows");
        return ['audit_logs' => $count];
    }

    private function exportAiInvocations(string $dir, ?string $from, string $to): array
    {
        $rows = collect();

        if (DB::getSchemaBuilder()->hasTable('ai_invocations')) {
            $query = DB::table('ai_invocations');
            if ($from) {
                $query->where('created_at', '>=', $from . ' 00:00:00');
            }
            $query->where('created_at', '<=', $to . ' 23:59:59');
            $rows = $query->orderBy('id')->get()->map(fn ($r) => (array) $r);
        }

        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'ai_invocations.json',
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $count = $rows->count();
        $this->line("  ai_invocations   : {$count} rows");
        return ['ai_invocations' => $count];
    }

    private function exportChainProof(string $dir): void
    {
        $exitCode = Artisan::call('audit:verify-chain', ['--chunk' => 500]);
        $output   = Artisan::output();

        $proof = [
            'generated_at' => now()->toIso8601String(),
            'exit_code'    => $exitCode,
            'result'       => $exitCode === 0 ? 'CHAIN_OK' : 'CHAIN_BROKEN',
            'output'       => trim($output),
        ];

        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'chain_verify.json',
            json_encode($proof, JSON_PRETTY_PRINT)
        );

        $this->line("  chain_verify     : {$proof['result']}");
    }

    private function exportFeatureFlags(string $dir): int
    {
        $flags = DB::table('platform_settings')
            ->where('provider', 'feature_flag')
            ->orderBy('platform_name')
            ->get(['platform_name', 'meta'])
            ->map(fn ($r) => [
                'flag'    => $r->platform_name,
                'enabled' => (bool) (json_decode($r->meta ?? '{}', true)['value'] ?? false),
            ]);

        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'feature_flags.json',
            json_encode($flags, JSON_PRETTY_PRINT)
        );

        $count = $flags->count();
        $this->line("  feature_flags    : {$count} flags");
        return $count;
    }

    private function exportManifest(string $dir, ?string $from, string $to, array $counts, int $flagCount): void
    {
        $manifest = [
            'exported_at'   => now()->toIso8601String(),
            'date_from'     => $from ?? 'all',
            'date_to'       => $to,
            'app_env'       => config('app.env'),
            'row_counts'    => array_merge($counts, ['feature_flags' => $flagCount]),
            'files'         => [
                'audit_logs.json',
                'ai_invocations.json',
                'chain_verify.json',
                'feature_flags.json',
                'manifest.json',
            ],
        ];

        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );
    }

    private function createZip(string $dir, ?string $from, string $to): string
    {
        $fromStr = $from ? str_replace('-', '', $from) : 'all';
        $toStr   = str_replace('-', '', $to);
        $ts      = now()->format('YmdHis');
        $name    = "evidence_{$fromStr}_{$toStr}_{$ts}.zip";

        $outDir = storage_path('app' . DIRECTORY_SEPARATOR . 'soc2');
        if (!is_dir($outDir)) {
            mkdir($outDir, 0750, true);
        }

        $zipPath = $outDir . DIRECTORY_SEPARATOR . $name;
        $zip     = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP at {$zipPath}");
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();
        return $zipPath;
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}
