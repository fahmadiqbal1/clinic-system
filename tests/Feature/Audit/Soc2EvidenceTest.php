<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class Soc2EvidenceTest extends TestCase
{
    use RefreshDatabase;

    private array $createdZips = [];

    protected function tearDown(): void
    {
        foreach ($this->createdZips as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $soc2Dir = storage_path('app/soc2');
        if (is_dir($soc2Dir) && count(glob($soc2Dir . '/*.zip')) === 0) {
            @rmdir($soc2Dir);
        }

        parent::tearDown();
    }

    private function findLatestZip(): ?string
    {
        $zips = glob(storage_path('app/soc2/evidence_*.zip')) ?: [];
        if (empty($zips)) {
            return null;
        }
        usort($zips, fn ($a, $b) => filemtime($b) - filemtime($a));
        $path = $zips[0];
        $this->createdZips[] = $path;
        return $path;
    }

    private function zipContents(string $zipPath): array
    {
        $zip   = new ZipArchive();
        $zip->open($zipPath);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        return $names;
    }

    private function zipFileJson(string $zipPath, string $file): mixed
    {
        $zip  = new ZipArchive();
        $zip->open($zipPath);
        $json = $zip->getFromName($file);
        $zip->close();
        return json_decode($json, true);
    }

    public function test_command_exits_success_with_valid_date_range(): void
    {
        $this->artisan('soc2:evidence', [
            '--from' => '2024-01-01',
            '--to'   => '2024-12-31',
        ])->assertExitCode(0);
    }

    public function test_command_creates_zip_file_in_storage(): void
    {
        $before = time();

        $this->artisan('soc2:evidence', [
            '--from' => '2024-01-01',
            '--to'   => '2024-12-31',
        ])->assertExitCode(0);

        $zip = $this->findLatestZip();
        $this->assertNotNull($zip, 'Expected ZIP file in storage/app/soc2/');
        $this->assertGreaterThanOrEqual($before, filemtime($zip));
    }

    public function test_zip_contains_all_required_files(): void
    {
        $this->artisan('soc2:evidence', [
            '--from' => '2024-01-01',
            '--to'   => '2024-12-31',
        ])->assertExitCode(0);

        $zip = $this->findLatestZip();
        $this->assertNotNull($zip);

        $contents = $this->zipContents($zip);

        foreach (['audit_logs.json', 'ai_invocations.json', 'chain_verify.json', 'feature_flags.json', 'manifest.json'] as $expected) {
            $this->assertContains($expected, $contents, "ZIP missing: {$expected}");
        }
    }

    public function test_command_rejects_invalid_date_format(): void
    {
        $this->artisan('soc2:evidence', ['--from' => 'not-a-date'])
             ->assertExitCode(1);
    }

    public function test_manifest_includes_correct_date_range(): void
    {
        $this->artisan('soc2:evidence', [
            '--from' => '2024-03-01',
            '--to'   => '2024-03-31',
        ])->assertExitCode(0);

        $zip = $this->findLatestZip();
        $this->assertNotNull($zip);

        $manifest = $this->zipFileJson($zip, 'manifest.json');

        $this->assertSame('2024-03-01', $manifest['date_from']);
        $this->assertSame('2024-03-31', $manifest['date_to']);
        $this->assertArrayHasKey('exported_at', $manifest);
        $this->assertArrayHasKey('row_counts', $manifest);
        $this->assertContains('manifest.json', $manifest['files']);
    }
}
