<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\PlatformSetting;
use App\Models\ServiceCatalog;
use App\Services\AiSidecarClient;
use Illuminate\Console\Command;

class RagflowSync extends Command
{
    protected $signature = 'ragflow:sync {--dry-run : Print corpus without sending}';

    protected $description = 'Sync service_catalog and non-financial inventory_items to RAGFlow via sidecar (nightly)';

    public function handle(AiSidecarClient $client): int
    {
        if (!PlatformSetting::isEnabled('ai.ragflow.enabled')) {
            $this->info('ragflow:sync skipped — ai.ragflow.enabled is OFF.');
            return self::SUCCESS;
        }

        $this->syncServiceCatalog($client);
        $this->syncInventory($client);

        return self::SUCCESS;
    }

    private function syncServiceCatalog(AiSidecarClient $client): void
    {
        $services = ServiceCatalog::active()
            ->get(['name', 'code', 'description', 'category', 'default_parameters']);

        $corpus = $services->map(fn (ServiceCatalog $s) => implode("\n", array_filter([
            "Service: {$s->name} [{$s->code}]",
            "Category: {$s->category}",
            $s->description ? "Description: {$s->description}" : null,
            $s->default_parameters
                ? 'Parameters: ' . json_encode($s->default_parameters, JSON_PRETTY_PRINT)
                : null,
        ])))->join("\n\n---\n\n");

        if ($this->option('dry-run')) {
            $this->line($corpus);
            return;
        }

        try {
            $client->ragIngestContent($corpus, 'service_catalog');
            $this->info("service_catalog synced ({$services->count()} services).");
        } catch (\Exception $e) {
            $this->error('service_catalog sync failed: ' . $e->getMessage());
        }
    }

    private function syncInventory(AiSidecarClient $client): void
    {
        $items = InventoryItem::where('is_active', true)
            ->get(['name', 'chemical_formula', 'unit', 'requires_prescription']);

        $corpus = $items->map(fn (InventoryItem $i) => implode("\n", [
            "Drug/Item: {$i->name}",
            'Formula: ' . ($i->chemical_formula ?? 'N/A'),
            'Unit: ' . ($i->unit ?? 'N/A'),
            'Prescription required: ' . ($i->requires_prescription ? 'Yes' : 'No'),
        ]))->join("\n\n---\n\n");

        if ($this->option('dry-run')) {
            $this->line($corpus);
            return;
        }

        try {
            $client->ragIngestContent($corpus, 'inventory');
            $this->info("inventory synced ({$items->count()} items).");
        } catch (\Exception $e) {
            $this->error('inventory sync failed: ' . $e->getMessage());
        }
    }
}
