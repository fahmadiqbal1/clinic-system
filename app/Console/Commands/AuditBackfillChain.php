<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditBackfillChain extends Command
{
    protected $signature   = 'audit:backfill-chain {--chunk=500 : Rows processed per batch}';
    protected $description = 'Backfill prev_hash / row_hash for existing audit_log rows (run before the immutability trigger is active)';

    public function handle(): int
    {
        $chunk    = (int) $this->option('chunk');
        $prevHash = '';
        $count    = 0;

        $total = DB::table('audit_logs')->count();
        $this->info("Backfilling hash chain for {$total} audit_log rows (chunk={$chunk})…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::table('audit_logs')->orderBy('id')->chunkById($chunk, function ($rows) use (&$prevHash, &$count, $bar) {
            foreach ($rows as $row) {
                $canonical = json_encode([
                    'user_id'        => $row->user_id,
                    'action'         => $row->action,
                    'auditable_type' => $row->auditable_type,
                    'auditable_id'   => $row->auditable_id,
                    'before_state'   => $row->before_state,
                    'after_state'    => $row->after_state,
                    'ip_address'     => $row->ip_address,
                    'user_agent'     => $row->user_agent ?? null,
                    'session_id'     => $row->session_id ?? null,
                    'created_at'     => $row->created_at,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $rowHash = hash('sha256', $prevHash . '|' . $canonical);

                DB::table('audit_logs')
                    ->where('id', $row->id)
                    ->update(['prev_hash' => $prevHash, 'row_hash' => $rowHash]);

                $prevHash = $rowHash;
                $count++;
            }
            $bar->advance(count($rows));
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$count} rows backfilled. Last row_hash: " . substr($prevHash, 0, 16) . '…');

        return self::SUCCESS;
    }
}
