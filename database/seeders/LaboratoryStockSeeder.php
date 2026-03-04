<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds laboratory inventory with common test kits and consumables,
 * initial stock movements, and sample procurement requests.
 *
 * Run: php artisan db:seed --class=LaboratoryStockSeeder
 */
class LaboratoryStockSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding laboratory stock...');

        // Find an Owner user for stock references
        $owner = User::role('Owner')->first();
        if (!$owner) {
            $this->command->error('No Owner user found. Create one first.');
            return;
        }

        // Find a Laboratory user for procurement requests
        $labUser = User::role('Laboratory')->first();

        $inventoryService = app(InventoryService::class);

        // ── Test Kits ──
        $testKits = [
            ['name' => 'CBC Test Kit (Complete Blood Count)', 'sku' => 'LAB-KIT-CBC', 'unit' => 'kit', 'purchase_price' => 120, 'selling_price' => 250, 'minimum_stock_level' => 50, 'initial_qty' => 200],
            ['name' => 'Blood Glucose Test Strips', 'sku' => 'LAB-KIT-BGL', 'unit' => 'strip', 'purchase_price' => 35, 'selling_price' => 80, 'minimum_stock_level' => 100, 'initial_qty' => 500],
            ['name' => 'Lipid Panel Reagent Kit', 'sku' => 'LAB-KIT-LPD', 'unit' => 'kit', 'purchase_price' => 200, 'selling_price' => 450, 'minimum_stock_level' => 30, 'initial_qty' => 100],
            ['name' => 'Liver Function Test Kit (LFT)', 'sku' => 'LAB-KIT-LFT', 'unit' => 'kit', 'purchase_price' => 180, 'selling_price' => 400, 'minimum_stock_level' => 30, 'initial_qty' => 80],
            ['name' => 'Renal Function Test Kit (RFT/KFT)', 'sku' => 'LAB-KIT-RFT', 'unit' => 'kit', 'purchase_price' => 160, 'selling_price' => 350, 'minimum_stock_level' => 30, 'initial_qty' => 90],
            ['name' => 'Thyroid Panel Kit (TSH/T3/T4)', 'sku' => 'LAB-KIT-THY', 'unit' => 'kit', 'purchase_price' => 250, 'selling_price' => 600, 'minimum_stock_level' => 20, 'initial_qty' => 60],
            ['name' => 'Urinalysis Dipstick Strips', 'sku' => 'LAB-KIT-URN', 'unit' => 'strip', 'purchase_price' => 12, 'selling_price' => 40, 'minimum_stock_level' => 200, 'initial_qty' => 1000],
            ['name' => 'Hepatitis B Surface Antigen (HBsAg) Kit', 'sku' => 'LAB-KIT-HBS', 'unit' => 'kit', 'purchase_price' => 90, 'selling_price' => 200, 'minimum_stock_level' => 25, 'initial_qty' => 80],
            ['name' => 'Hepatitis C Rapid Test Kit', 'sku' => 'LAB-KIT-HCV', 'unit' => 'kit', 'purchase_price' => 95, 'selling_price' => 220, 'minimum_stock_level' => 25, 'initial_qty' => 80],
            ['name' => 'HIV Rapid Test Kit', 'sku' => 'LAB-KIT-HIV', 'unit' => 'kit', 'purchase_price' => 110, 'selling_price' => 250, 'minimum_stock_level' => 20, 'initial_qty' => 50],
            ['name' => 'Blood Culture Bottles', 'sku' => 'LAB-KIT-BCL', 'unit' => 'bottle', 'purchase_price' => 150, 'selling_price' => 350, 'minimum_stock_level' => 20, 'initial_qty' => 40],
            ['name' => 'ESR (Westergren) Tubes', 'sku' => 'LAB-KIT-ESR', 'unit' => 'tube', 'purchase_price' => 25, 'selling_price' => 60, 'minimum_stock_level' => 50, 'initial_qty' => 200],
            ['name' => 'Pregnancy Test (hCG) Kit', 'sku' => 'LAB-KIT-HCG', 'unit' => 'kit', 'purchase_price' => 30, 'selling_price' => 80, 'minimum_stock_level' => 30, 'initial_qty' => 100],
            ['name' => 'Dengue NS1 Antigen Rapid Kit', 'sku' => 'LAB-KIT-DNG', 'unit' => 'kit', 'purchase_price' => 130, 'selling_price' => 300, 'minimum_stock_level' => 20, 'initial_qty' => 60],
            ['name' => 'Malaria Rapid Diagnostic Kit', 'sku' => 'LAB-KIT-MAL', 'unit' => 'kit', 'purchase_price' => 70, 'selling_price' => 150, 'minimum_stock_level' => 25, 'initial_qty' => 80],
        ];

        // ── Consumables & Supplies ──
        $consumables = [
            ['name' => 'EDTA Vacutainer Tubes (Purple Top)', 'sku' => 'LAB-CON-EDTA', 'unit' => 'tube', 'purchase_price' => 18, 'selling_price' => 18, 'minimum_stock_level' => 200, 'initial_qty' => 1000],
            ['name' => 'Plain Vacutainer Tubes (Red Top)', 'sku' => 'LAB-CON-PLAIN', 'unit' => 'tube', 'purchase_price' => 15, 'selling_price' => 15, 'minimum_stock_level' => 200, 'initial_qty' => 1000],
            ['name' => 'SST Vacutainer Tubes (Gold Top)', 'sku' => 'LAB-CON-SST', 'unit' => 'tube', 'purchase_price' => 22, 'selling_price' => 22, 'minimum_stock_level' => 150, 'initial_qty' => 600],
            ['name' => 'Citrate Vacutainer Tubes (Blue Top)', 'sku' => 'LAB-CON-CIT', 'unit' => 'tube', 'purchase_price' => 20, 'selling_price' => 20, 'minimum_stock_level' => 100, 'initial_qty' => 400],
            ['name' => 'Disposable Syringes (5ml)', 'sku' => 'LAB-CON-SYR5', 'unit' => 'piece', 'purchase_price' => 8, 'selling_price' => 8, 'minimum_stock_level' => 300, 'initial_qty' => 1500],
            ['name' => 'Disposable Syringes (10ml)', 'sku' => 'LAB-CON-SYR10', 'unit' => 'piece', 'purchase_price' => 10, 'selling_price' => 10, 'minimum_stock_level' => 200, 'initial_qty' => 1000],
            ['name' => 'Butterfly Needles (23G)', 'sku' => 'LAB-CON-BTN', 'unit' => 'piece', 'purchase_price' => 25, 'selling_price' => 25, 'minimum_stock_level' => 100, 'initial_qty' => 500],
            ['name' => 'Latex Examination Gloves (Box of 100)', 'sku' => 'LAB-CON-GLV', 'unit' => 'box', 'purchase_price' => 450, 'selling_price' => 450, 'minimum_stock_level' => 10, 'initial_qty' => 30],
            ['name' => 'Microscope Slides (Box of 72)', 'sku' => 'LAB-CON-SLD', 'unit' => 'box', 'purchase_price' => 180, 'selling_price' => 180, 'minimum_stock_level' => 5, 'initial_qty' => 20],
            ['name' => 'Cover Slips (Box of 100)', 'sku' => 'LAB-CON-CVR', 'unit' => 'box', 'purchase_price' => 120, 'selling_price' => 120, 'minimum_stock_level' => 5, 'initial_qty' => 15],
            ['name' => 'Cotton Balls (Pack of 500)', 'sku' => 'LAB-CON-CTN', 'unit' => 'pack', 'purchase_price' => 200, 'selling_price' => 200, 'minimum_stock_level' => 5, 'initial_qty' => 15],
            ['name' => 'Spirit Swabs / Alcohol Prep Pads (Box)', 'sku' => 'LAB-CON-ALC', 'unit' => 'box', 'purchase_price' => 250, 'selling_price' => 250, 'minimum_stock_level' => 10, 'initial_qty' => 30],
            ['name' => 'Tourniquet Bands', 'sku' => 'LAB-CON-TRQ', 'unit' => 'piece', 'purchase_price' => 40, 'selling_price' => 40, 'minimum_stock_level' => 20, 'initial_qty' => 50],
            ['name' => 'Urine Collection Cups (Sterile)', 'sku' => 'LAB-CON-UCU', 'unit' => 'piece', 'purchase_price' => 15, 'selling_price' => 15, 'minimum_stock_level' => 100, 'initial_qty' => 500],
            ['name' => 'Stool Sample Containers', 'sku' => 'LAB-CON-STL', 'unit' => 'piece', 'purchase_price' => 12, 'selling_price' => 12, 'minimum_stock_level' => 50, 'initial_qty' => 200],
        ];

        $allItems = array_merge($testKits, $consumables);
        $createdItems = [];

        DB::transaction(function () use ($allItems, $owner, $inventoryService, &$createdItems) {
            foreach ($allItems as $data) {
                $initialQty = $data['initial_qty'];
                unset($data['initial_qty']);

                $item = InventoryItem::updateOrCreate(
                    ['sku' => $data['sku']],
                    array_merge($data, [
                        'department' => 'laboratory',
                        'is_active' => true,
                        'weighted_avg_cost' => $data['purchase_price'],
                    ])
                );

                // Record initial stock if no movements exist yet
                $existingStock = StockMovement::where('inventory_item_id', $item->id)->exists();
                if (!$existingStock) {
                    $inventoryService->recordInbound(
                        $item,
                        $initialQty,
                        $data['purchase_price'],
                        'seeder',
                        0,
                        $owner
                    );
                }

                $createdItems[] = $item;
            }
        });

        $this->command->info('Created/updated ' . count($createdItems) . ' inventory items with initial stock.');

        // ── Sample Procurement Requests ──
        if ($labUser) {
            $this->seedSampleProcurements($labUser, $owner, $createdItems);
        } else {
            $this->command->warn('No Laboratory user found — skipping sample procurement requests.');
        }
    }

    /**
     * Seed 3 sample procurement requests in different states.
     */
    protected function seedSampleProcurements(User $labUser, User $owner, array $items): void
    {
        // 1. Pending procurement — lab tech needs more CBC kits
        $pending = ProcurementRequest::create([
            'department' => 'laboratory',
            'type' => 'inventory',
            'requested_by' => $labUser->id,
            'status' => 'pending',
            'notes' => 'Running low on CBC kits and EDTA tubes. Need restock before month end.',
        ]);

        $cbcItem = collect($items)->firstWhere('sku', 'LAB-KIT-CBC');
        $edtaItem = collect($items)->firstWhere('sku', 'LAB-CON-EDTA');

        if ($cbcItem) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $pending->id,
                'inventory_item_id' => $cbcItem->id,
                'quantity_requested' => 100,
                'unit_price' => 120,
            ]);
        }
        if ($edtaItem) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $pending->id,
                'inventory_item_id' => $edtaItem->id,
                'quantity_requested' => 500,
                'unit_price' => 18,
            ]);
        }

        // 2. Approved procurement — waiting for delivery
        $approved = ProcurementRequest::create([
            'department' => 'laboratory',
            'type' => 'inventory',
            'requested_by' => $labUser->id,
            'approved_by' => $owner->id,
            'status' => 'approved',
            'notes' => 'Approved for quarterly restock of rapid test kits.',
        ]);

        $hbsItem = collect($items)->firstWhere('sku', 'LAB-KIT-HBS');
        $hcvItem = collect($items)->firstWhere('sku', 'LAB-KIT-HCV');

        if ($hbsItem) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $approved->id,
                'inventory_item_id' => $hbsItem->id,
                'quantity_requested' => 50,
                'unit_price' => 90,
            ]);
        }
        if ($hcvItem) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $approved->id,
                'inventory_item_id' => $hcvItem->id,
                'quantity_requested' => 50,
                'unit_price' => 95,
            ]);
        }

        // 3. Received procurement — already fulfilled
        $received = ProcurementRequest::create([
            'department' => 'laboratory',
            'type' => 'inventory',
            'requested_by' => $labUser->id,
            'approved_by' => $owner->id,
            'status' => 'received',
            'notes' => 'Gloves and syringes restocked successfully.',
        ]);

        $glvItem = collect($items)->firstWhere('sku', 'LAB-CON-GLV');
        $syrItem = collect($items)->firstWhere('sku', 'LAB-CON-SYR5');

        if ($glvItem) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $received->id,
                'inventory_item_id' => $glvItem->id,
                'quantity_requested' => 20,
                'quantity_received' => 20,
                'unit_price' => 450,
            ]);
        }
        if ($syrItem) {
            ProcurementRequestItem::create([
                'procurement_request_id' => $received->id,
                'inventory_item_id' => $syrItem->id,
                'quantity_requested' => 1000,
                'quantity_received' => 1000,
                'unit_price' => 8,
            ]);
        }

        $this->command->info('Created 3 sample procurement requests (pending, approved, received).');
    }
}
