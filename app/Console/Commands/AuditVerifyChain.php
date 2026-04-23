<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditVerifyChain extends Command
{
    protected $signature   = 'audit:verify-chain {--chunk=500 : Rows verified per batch}';
    protected $description = 'Verify the audit_logs hash chain integrity — exits non-zero if any row is tampered';

    public function handle(): int
    {
        $chunk       = (int) $this->option('chunk');
        $prevHash    = '';
        $verified    = 0;
        $firstFail   = null;

        $total = DB::table('audit_logs')->count();

        if ($total === 0) {
            $this->info('audit_logs is empty — chain OK.');
            return self::SUCCESS;
        }

        $this->info("Verifying hash chain across {$total} audit_log rows (chunk={$chunk})…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::table('audit_logs')->orderBy('id')->chunkById($chunk, function ($rows) use (&$prevHash, &$verified, &$firstFail, $bar) {
            if ($firstFail !== null) {
                return false; // stop chunking once a failure is found
            }

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

                $expectedRowHash  = hash('sha256', $prevHash . '|' . $canonical);
                $expectedPrevHash = $prevHash;

                if ($row->row_hash !== $expectedRowHash || $row->prev_hash !== $expectedPrevHash) {
                    $firstFail = $row->id;
                    return false;
                }

                $prevHash = $row->row_hash;
                $verified++;
            }
            $bar->advance(count($rows));
        });

        $bar->finish();
        $this->newLine();

        if ($firstFail !== null) {
            $this->error("CHAIN BROKEN at audit_log id={$firstFail}. Tamper detected or backfill incomplete.");
            return self::FAILURE;
        }

        $this->info("Chain OK — {$verified} rows verified. Last row_hash: " . substr($prevHash, 0, 16) . '…');
        return self::SUCCESS;
    }
}
