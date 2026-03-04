<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds pharmacy inventory with common medications and initial stock.
 *
 * Run: php artisan db:seed --class=PharmacyStockSeeder
 */
class PharmacyStockSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding pharmacy stock...');

        $owner = User::role('Owner')->first();
        if (!$owner) {
            $this->command->error('No Owner user found. Run DatabaseSeeder first.');
            return;
        }

        $inventoryService = app(InventoryService::class);

        $medications = [
            // Analgesics & Antipyretics
            ['name' => 'Paracetamol 500mg Tablets', 'sku' => 'PH-PCM500', 'unit' => 'tablet', 'purchase_price' => 2, 'selling_price' => 5, 'minimum_stock_level' => 500, 'initial_qty' => 2000],
            ['name' => 'Ibuprofen 400mg Tablets', 'sku' => 'PH-IBU400', 'unit' => 'tablet', 'purchase_price' => 4, 'selling_price' => 10, 'minimum_stock_level' => 300, 'initial_qty' => 1000],
            ['name' => 'Diclofenac 50mg Tablets', 'sku' => 'PH-DCL050', 'unit' => 'tablet', 'purchase_price' => 5, 'selling_price' => 12, 'minimum_stock_level' => 200, 'initial_qty' => 800],
            ['name' => 'Aspirin 300mg Tablets', 'sku' => 'PH-ASP300', 'unit' => 'tablet', 'purchase_price' => 3, 'selling_price' => 8, 'minimum_stock_level' => 200, 'initial_qty' => 600],
            ['name' => 'Tramadol 50mg Capsules', 'sku' => 'PH-TRM050', 'unit' => 'capsule', 'purchase_price' => 10, 'selling_price' => 25, 'minimum_stock_level' => 100, 'initial_qty' => 300],

            // Antibiotics
            ['name' => 'Amoxicillin 500mg Capsules', 'sku' => 'PH-AMX500', 'unit' => 'capsule', 'purchase_price' => 8, 'selling_price' => 20, 'minimum_stock_level' => 300, 'initial_qty' => 1000],
            ['name' => 'Azithromycin 500mg Tablets', 'sku' => 'PH-AZT500', 'unit' => 'tablet', 'purchase_price' => 15, 'selling_price' => 35, 'minimum_stock_level' => 100, 'initial_qty' => 500],
            ['name' => 'Ciprofloxacin 500mg Tablets', 'sku' => 'PH-CIP500', 'unit' => 'tablet', 'purchase_price' => 10, 'selling_price' => 25, 'minimum_stock_level' => 200, 'initial_qty' => 600],
            ['name' => 'Metronidazole 400mg Tablets', 'sku' => 'PH-MTZ400', 'unit' => 'tablet', 'purchase_price' => 6, 'selling_price' => 15, 'minimum_stock_level' => 200, 'initial_qty' => 800],
            ['name' => 'Doxycycline 100mg Capsules', 'sku' => 'PH-DOX100', 'unit' => 'capsule', 'purchase_price' => 7, 'selling_price' => 18, 'minimum_stock_level' => 150, 'initial_qty' => 400],
            ['name' => 'Cephalexin 500mg Capsules', 'sku' => 'PH-CPH500', 'unit' => 'capsule', 'purchase_price' => 12, 'selling_price' => 28, 'minimum_stock_level' => 150, 'initial_qty' => 400],
            ['name' => 'Augmentin 625mg Tablets', 'sku' => 'PH-AUG625', 'unit' => 'tablet', 'purchase_price' => 20, 'selling_price' => 45, 'minimum_stock_level' => 100, 'initial_qty' => 300],

            // Gastrointestinal
            ['name' => 'Omeprazole 20mg Capsules', 'sku' => 'PH-OMP020', 'unit' => 'capsule', 'purchase_price' => 6, 'selling_price' => 15, 'minimum_stock_level' => 200, 'initial_qty' => 800],
            ['name' => 'Ranitidine 150mg Tablets', 'sku' => 'PH-RAN150', 'unit' => 'tablet', 'purchase_price' => 4, 'selling_price' => 10, 'minimum_stock_level' => 200, 'initial_qty' => 600],
            ['name' => 'Loperamide 2mg Capsules', 'sku' => 'PH-LOP002', 'unit' => 'capsule', 'purchase_price' => 5, 'selling_price' => 12, 'minimum_stock_level' => 100, 'initial_qty' => 300],
            ['name' => 'Oral Rehydration Salts (ORS)', 'sku' => 'PH-ORS001', 'unit' => 'sachet', 'purchase_price' => 8, 'selling_price' => 20, 'minimum_stock_level' => 200, 'initial_qty' => 500],

            // Antihistamines & Anti-allergy
            ['name' => 'Cetirizine 10mg Tablets', 'sku' => 'PH-CTZ010', 'unit' => 'tablet', 'purchase_price' => 3, 'selling_price' => 8, 'minimum_stock_level' => 200, 'initial_qty' => 600],
            ['name' => 'Loratadine 10mg Tablets', 'sku' => 'PH-LOR010', 'unit' => 'tablet', 'purchase_price' => 4, 'selling_price' => 10, 'minimum_stock_level' => 200, 'initial_qty' => 500],
            ['name' => 'Chlorpheniramine 4mg Tablets', 'sku' => 'PH-CPM004', 'unit' => 'tablet', 'purchase_price' => 2, 'selling_price' => 5, 'minimum_stock_level' => 200, 'initial_qty' => 800],

            // Antihypertensives & Cardiovascular
            ['name' => 'Amlodipine 5mg Tablets', 'sku' => 'PH-AML005', 'unit' => 'tablet', 'purchase_price' => 5, 'selling_price' => 12, 'minimum_stock_level' => 200, 'initial_qty' => 600],
            ['name' => 'Losartan 50mg Tablets', 'sku' => 'PH-LOS050', 'unit' => 'tablet', 'purchase_price' => 8, 'selling_price' => 20, 'minimum_stock_level' => 200, 'initial_qty' => 500],
            ['name' => 'Atenolol 50mg Tablets', 'sku' => 'PH-ATN050', 'unit' => 'tablet', 'purchase_price' => 4, 'selling_price' => 10, 'minimum_stock_level' => 200, 'initial_qty' => 500],

            // Antidiabetics
            ['name' => 'Metformin 500mg Tablets', 'sku' => 'PH-MET500', 'unit' => 'tablet', 'purchase_price' => 3, 'selling_price' => 8, 'minimum_stock_level' => 300, 'initial_qty' => 1000],
            ['name' => 'Glibenclamide 5mg Tablets', 'sku' => 'PH-GLB005', 'unit' => 'tablet', 'purchase_price' => 5, 'selling_price' => 12, 'minimum_stock_level' => 200, 'initial_qty' => 500],

            // Respiratory
            ['name' => 'Salbutamol Inhaler 100mcg', 'sku' => 'PH-SAL100', 'unit' => 'inhaler', 'purchase_price' => 150, 'selling_price' => 350, 'minimum_stock_level' => 20, 'initial_qty' => 50],
            ['name' => 'Prednisolone 5mg Tablets', 'sku' => 'PH-PRD005', 'unit' => 'tablet', 'purchase_price' => 6, 'selling_price' => 15, 'minimum_stock_level' => 200, 'initial_qty' => 500],
            ['name' => 'Cough Syrup (Dextromethorphan)', 'sku' => 'PH-CGH001', 'unit' => 'bottle', 'purchase_price' => 80, 'selling_price' => 180, 'minimum_stock_level' => 30, 'initial_qty' => 100],

            // Antimalarials
            ['name' => 'Artemether/Lumefantrine (AL) Tablets', 'sku' => 'PH-ALU001', 'unit' => 'pack', 'purchase_price' => 60, 'selling_price' => 150, 'minimum_stock_level' => 50, 'initial_qty' => 200],
            ['name' => 'Quinine 300mg Tablets', 'sku' => 'PH-QUI300', 'unit' => 'tablet', 'purchase_price' => 10, 'selling_price' => 25, 'minimum_stock_level' => 100, 'initial_qty' => 300],

            // Vitamins & Supplements
            ['name' => 'Multivitamin Tablets', 'sku' => 'PH-MUL001', 'unit' => 'tablet', 'purchase_price' => 5, 'selling_price' => 12, 'minimum_stock_level' => 200, 'initial_qty' => 500],
            ['name' => 'Ferrous Sulphate 200mg Tablets', 'sku' => 'PH-FER200', 'unit' => 'tablet', 'purchase_price' => 3, 'selling_price' => 8, 'minimum_stock_level' => 200, 'initial_qty' => 600],
            ['name' => 'Folic Acid 5mg Tablets', 'sku' => 'PH-FOL005', 'unit' => 'tablet', 'purchase_price' => 2, 'selling_price' => 5, 'minimum_stock_level' => 200, 'initial_qty' => 800],
            ['name' => 'Vitamin C 500mg Tablets', 'sku' => 'PH-VTC500', 'unit' => 'tablet', 'purchase_price' => 4, 'selling_price' => 10, 'minimum_stock_level' => 200, 'initial_qty' => 600],
            ['name' => 'Calcium + Vitamin D Tablets', 'sku' => 'PH-CAD001', 'unit' => 'tablet', 'purchase_price' => 6, 'selling_price' => 15, 'minimum_stock_level' => 150, 'initial_qty' => 400],

            // Topical / External
            ['name' => 'Hydrocortisone Cream 1%', 'sku' => 'PH-HYD001', 'unit' => 'tube', 'purchase_price' => 40, 'selling_price' => 90, 'minimum_stock_level' => 30, 'initial_qty' => 100],
            ['name' => 'Clotrimazole Cream 1%', 'sku' => 'PH-CLT001', 'unit' => 'tube', 'purchase_price' => 35, 'selling_price' => 80, 'minimum_stock_level' => 30, 'initial_qty' => 100],
            ['name' => 'Povidone Iodine Solution 10%', 'sku' => 'PH-PVD010', 'unit' => 'bottle', 'purchase_price' => 50, 'selling_price' => 120, 'minimum_stock_level' => 20, 'initial_qty' => 60],

            // Eye / Ear Drops
            ['name' => 'Chloramphenicol Eye Drops 0.5%', 'sku' => 'PH-CLE001', 'unit' => 'bottle', 'purchase_price' => 30, 'selling_price' => 70, 'minimum_stock_level' => 30, 'initial_qty' => 80],
            ['name' => 'Ciprofloxacin Eye Drops 0.3%', 'sku' => 'PH-CIE001', 'unit' => 'bottle', 'purchase_price' => 45, 'selling_price' => 100, 'minimum_stock_level' => 20, 'initial_qty' => 60],
        ];

        $createdItems = [];

        DB::transaction(function () use ($medications, $owner, $inventoryService, &$createdItems) {
            foreach ($medications as $data) {
                $initialQty = $data['initial_qty'];
                unset($data['initial_qty']);

                $item = InventoryItem::updateOrCreate(
                    ['sku' => $data['sku']],
                    array_merge($data, [
                        'department' => 'pharmacy',
                        'is_active' => true,
                        'weighted_avg_cost' => $data['purchase_price'],
                    ])
                );

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

        $this->command->info('Created/updated ' . count($createdItems) . ' pharmacy items with initial stock.');
    }
}
