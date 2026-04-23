<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GitnexusImpact extends Command
{
    protected $signature = 'gitnexus:impact
                            {target : File path or class/symbol name to analyse}
                            {--depth=3 : Max relationship depth}
                            {--include-tests : Include test files in results}';

    protected $description = 'Blast-radius analysis: list all callers/dependants of a symbol';

    public function handle(): int
    {
        $target = $this->argument('target');

        $args = [
            'gitnexus', 'impact', $target,
            '--repo', 'clinic-system',
            '--depth', (string) $this->option('depth'),
        ];
        if ($this->option('include-tests')) {
            $args[] = '--include-tests';
        }

        $process = new Process($args, base_path());
        $process->setTimeout(60);
        $process->run();

        $raw = $process->getOutput() ?: $process->getErrorOutput();
        $data = json_decode($raw, true);

        if (isset($data['error'])) {
            $this->error($data['error']);
            return self::FAILURE;
        }

        if (! $data) {
            $this->line($raw);
            return self::SUCCESS;
        }

        $this->renderImpact($data);
        return self::SUCCESS;
    }

    private function renderImpact(array $data): void
    {
        $target = $data['target'] ?? [];
        $this->line('');
        $this->line(sprintf(
            '<options=bold>Target:</> %s  <fg=yellow>%s</>  [%s]',
            $target['name'] ?? '?',
            $target['type'] ?? '',
            $target['filePath'] ?? ''
        ));
        $this->line(sprintf(
            '<options=bold>Risk:</> <fg=%s>%s</>   Impacted: <options=bold>%d</>   (direct: %d)',
            $this->riskColour($data['risk'] ?? ''),
            $data['risk'] ?? 'UNKNOWN',
            $data['impactedCount'] ?? 0,
            $data['summary']['direct'] ?? 0,
        ));
        $this->line('');

        foreach ($data['byDepth'] ?? [] as $depth => $items) {
            $this->line(sprintf('<fg=cyan>Depth %s (%d):</>', $depth, count($items)));
            $rows = array_map(fn($n) => [
                $n['depth'],
                $n['name'] ?? '?',
                $n['filePath'] ?? '?',
                $n['relationType'] ?? '',
            ], $items);
            $this->table(['Depth', 'Name', 'File', 'Relation'], $rows);
        }
    }

    private function riskColour(string $risk): string
    {
        return match ($risk) {
            'CRITICAL' => 'red',
            'HIGH'     => 'yellow',
            'MEDIUM'   => 'blue',
            default    => 'white',
        };
    }
}
