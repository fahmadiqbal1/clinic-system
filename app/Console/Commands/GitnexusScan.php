<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GitnexusScan extends Command
{
    protected $signature = 'gitnexus:scan {--force : Force full re-index even if up to date}';
    protected $description = 'Index codebase with GitNexus and emit storage/gitnexus/graph.json';

    public function handle(): int
    {
        $this->info('Running GitNexus analysis…');

        $args = ['gitnexus', 'analyze', '.', '--skip-agents-md'];
        if ($this->option('force')) {
            $args[] = '--force';
        }

        $analyze = new Process($args, base_path());
        $analyze->setTimeout(300);
        $analyze->run(function ($type, $line) {
            $this->getOutput()->write($line);
        });

        if (! $analyze->isSuccessful()) {
            $this->error('gitnexus analyze failed: ' . $analyze->getErrorOutput());
            return self::FAILURE;
        }

        $this->info('Building graph.json…');

        $meta = $this->readMeta();
        $nodes = $this->queryNodes();
        $edges = $this->queryEdges();

        if ($nodes === null || $edges === null) {
            $this->error('Failed to query GitNexus graph data.');
            return self::FAILURE;
        }

        $graph = [
            'meta'     => $meta,
            'elements' => ['nodes' => $nodes, 'edges' => $edges],
        ];

        $dir = storage_path('gitnexus');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(storage_path('gitnexus/graph.json'), json_encode($graph, JSON_PRETTY_PRINT));

        $this->info(sprintf(
            'graph.json written — %d nodes, %d edges.',
            count($nodes),
            count($edges)
        ));

        return self::SUCCESS;
    }

    private function readMeta(): array
    {
        $metaFile = base_path('.gitnexus/meta.json');
        if (file_exists($metaFile)) {
            return json_decode(file_get_contents($metaFile), true) ?? [];
        }
        return [];
    }

    private function queryNodes(): ?array
    {
        $result = $this->cypher(
            "MATCH (n:Class) WHERE n.filePath STARTS WITH 'app/' " .
            'RETURN n.id AS id, n.name AS name, n.filePath AS filePath'
        );
        if ($result === null) {
            return null;
        }

        $nodes = [];
        foreach ($result as $row) {
            $nodes[] = [
                'data' => [
                    'id'    => $row['id'],
                    'label' => $row['name'],
                    'type'  => $this->classifyPath($row['filePath']),
                    'file'  => $row['filePath'],
                ],
            ];
        }
        return $nodes;
    }

    private function queryEdges(): ?array
    {
        $result = $this->cypher(
            "MATCH (a:Class)-[r]->(b:Class) " .
            "WHERE a.filePath STARTS WITH 'app/' AND b.filePath STARTS WITH 'app/' " .
            'RETURN a.id AS source, b.id AS target'
        );
        if ($result === null) {
            return null;
        }

        $edges = [];
        foreach ($result as $i => $row) {
            $edges[] = [
                'data' => [
                    'id'     => 'e' . $i,
                    'source' => $row['source'],
                    'target' => $row['target'],
                ],
            ];
        }
        return $edges;
    }

    /** Run a gitnexus cypher query and return rows as associative arrays. */
    private function cypher(string $query): ?array
    {
        $process = new Process(
            ['gitnexus', 'cypher', $query, '--repo', 'clinic-system'],
            base_path()
        );
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $payload = json_decode($process->getOutput(), true);
        if (! isset($payload['markdown'])) {
            return null;
        }

        return $this->parseMarkdownTable($payload['markdown']);
    }

    /** Parse a GFM markdown table into rows of associative arrays. */
    private function parseMarkdownTable(string $markdown): array
    {
        $lines = array_filter(array_map('trim', explode("\n", trim($markdown))));
        $lines = array_values($lines);

        if (count($lines) < 2) {
            return [];
        }

        $headers = array_map('trim', explode('|', trim($lines[0], '|')));
        // Skip the separator row (line 1) and data starts at line 2
        $rows = [];
        for ($i = 2; $i < count($lines); $i++) {
            $cells = array_map('trim', explode('|', trim($lines[$i], '|')));
            if (count($cells) === count($headers)) {
                $rows[] = array_combine($headers, $cells);
            }
        }
        return $rows;
    }

    private function classifyPath(string $path): string
    {
        return match (true) {
            str_starts_with($path, 'app/Models/')              => 'model',
            str_starts_with($path, 'app/Services/')            => 'service',
            str_starts_with($path, 'app/Http/Controllers/')    => 'controller',
            str_starts_with($path, 'app/Http/Middleware/')     => 'middleware',
            str_starts_with($path, 'app/Console/Commands/')    => 'command',
            str_starts_with($path, 'app/Jobs/')                => 'job',
            str_starts_with($path, 'app/Policies/')            => 'policy',
            str_starts_with($path, 'app/Support/')             => 'support',
            str_starts_with($path, 'app/Enums/')               => 'enum',
            str_starts_with($path, 'app/Events/')              => 'event',
            str_starts_with($path, 'app/Listeners/')           => 'listener',
            str_starts_with($path, 'app/Providers/')           => 'provider',
            default                                            => 'other',
        };
    }
}
