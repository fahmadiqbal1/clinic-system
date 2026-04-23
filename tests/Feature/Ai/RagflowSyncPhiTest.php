<?php

namespace Tests\Feature\Ai;

use App\Models\InventoryItem;
use App\Models\PlatformSetting;
use App\Models\ServiceCatalog;
use App\Services\AiSidecarClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asserts that ragflow:sync never includes financial/PHI columns in the corpus.
 * Service catalog and inventory are clinical metadata — these tests are canaries:
 * if sensitive data ever leaks into these tables or the sync query, we catch it here.
 */
class RagflowSyncPhiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_corpus_excludes_financial_columns(): void
    {
        $this->enableFlag('ai.ragflow.enabled');

        InventoryItem::create([
            'department'            => 'pharmacy',
            'name'                  => 'Paracetamol 500mg',
            'chemical_formula'      => 'C8H9NO2',
            'sku'                   => 'PARA-500',
            'unit'                  => 'tablet',
            'minimum_stock_level'   => 100,
            'purchase_price'        => 12.50,
            'selling_price'         => 15.00,
            'weighted_avg_cost'     => 12.75,
            'requires_prescription' => false,
            'is_active'             => true,
        ]);

        $capturedContent = [];

        $this->mock(AiSidecarClient::class, function ($mock) use (&$capturedContent) {
            $mock->shouldReceive('isCircuitOpen')->andReturn(false);
            $mock->shouldReceive('ragIngestContent')
                 ->andReturnUsing(function (string $content, string $collection) use (&$capturedContent) {
                     $capturedContent[$collection] = $content;
                     return ['status' => 'queued'];
                 });
        });

        $this->artisan('ragflow:sync')->assertSuccessful();

        $inventoryCorpus = $capturedContent['inventory'] ?? '';

        // Non-financial columns MUST be present
        $this->assertStringContainsString('Paracetamol 500mg', $inventoryCorpus);
        $this->assertStringContainsString('C8H9NO2', $inventoryCorpus);

        // Financial columns MUST NOT appear
        $this->assertStringNotContainsString('12.50', $inventoryCorpus, 'purchase_price must not be in corpus');
        $this->assertStringNotContainsString('15.00', $inventoryCorpus, 'selling_price must not be in corpus');
        $this->assertStringNotContainsString('12.75', $inventoryCorpus, 'weighted_avg_cost must not be in corpus');
    }

    public function test_service_catalog_corpus_contains_only_clinical_metadata(): void
    {
        $this->enableFlag('ai.ragflow.enabled');

        ServiceCatalog::create([
            'department'         => 'lab',
            'name'               => 'Complete Blood Count',
            'code'               => 'LAB-CBC',
            'description'        => 'Full haematology panel.',
            'category'           => 'Haematology',
            'price'              => 850.00,
            'is_active'          => true,
            'default_parameters' => [
                ['name' => 'Haemoglobin', 'unit' => 'g/dL', 'range' => '12-17'],
            ],
        ]);

        $capturedContent = [];

        $this->mock(AiSidecarClient::class, function ($mock) use (&$capturedContent) {
            $mock->shouldReceive('isCircuitOpen')->andReturn(false);
            $mock->shouldReceive('ragIngestContent')
                 ->andReturnUsing(function (string $content, string $collection) use (&$capturedContent) {
                     $capturedContent[$collection] = $content;
                     return ['status' => 'queued'];
                 });
        });

        $this->artisan('ragflow:sync')->assertSuccessful();

        $catalogCorpus = $capturedContent['service_catalog'] ?? '';

        // Clinical metadata MUST be present
        $this->assertStringContainsString('Complete Blood Count', $catalogCorpus);
        $this->assertStringContainsString('LAB-CBC', $catalogCorpus);
        $this->assertStringContainsString('Haematology', $catalogCorpus);

        // PHI field names that must never appear (canary check)
        $phiFields = ['first_name', 'last_name', 'cnic', 'date_of_birth', 'consultation_notes'];
        foreach ($phiFields as $field) {
            $this->assertStringNotContainsStringIgnoringCase(
                $field,
                $catalogCorpus,
                "Corpus must not reference PHI field '{$field}'"
            );
        }
    }

    public function test_sync_skips_when_flag_off(): void
    {
        $this->disableFlag('ai.ragflow.enabled');

        $this->mock(AiSidecarClient::class, function ($mock) {
            $mock->shouldReceive('ragIngestContent')->never();
        });

        $this->artisan('ragflow:sync')->assertSuccessful();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function enableFlag(string $flag): void
    {
        PlatformSetting::updateOrCreate(
            ['platform_name' => $flag, 'provider' => 'feature_flag'],
            ['meta' => ['value' => true]]
        );
    }

    private function disableFlag(string $flag): void
    {
        PlatformSetting::updateOrCreate(
            ['platform_name' => $flag, 'provider' => 'feature_flag'],
            ['meta' => ['value' => false]]
        );
    }
}
