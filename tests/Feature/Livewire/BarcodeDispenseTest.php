<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Pharmacy\BarcodeDispense;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\InventoryService;
use Livewire\Livewire;
use Tests\TestCase;

class BarcodeDispenseTest extends TestCase
{
    private User $pharmacist;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pharmacist = User::factory()->create();
        $this->pharmacist->assignRole('Pharmacy');
        $this->inventory = app(InventoryService::class);
    }

    public function test_scan_barcode_finds_active_item_and_shows_stock(): void
    {
        $item = InventoryItem::factory()->create([
            'barcode'   => 'TEST-BC-001',
            'is_active' => true,
            'name'      => 'Paracetamol 500mg',
        ]);

        $this->inventory->recordInbound(
            item:          $item,
            quantity:      50,
            unitCost:      5.00,
            referenceType: 'procurement_request',
            referenceId:   1,
            user:          $this->pharmacist,
        );

        Livewire::actingAs($this->pharmacist)
            ->test(BarcodeDispense::class)
            ->set('barcode', 'TEST-BC-001')
            ->call('scanBarcode')
            ->assertSet('currentStock', 50)
            ->assertSet('message', '');
    }

    public function test_dispense_creates_outbound_movement_and_decrements_stock(): void
    {
        $item = InventoryItem::factory()->create([
            'barcode'   => 'TEST-BC-002',
            'is_active' => true,
        ]);

        $this->inventory->recordInbound(
            item:          $item,
            quantity:      100,
            unitCost:      3.00,
            referenceType: 'procurement_request',
            referenceId:   1,
            user:          $this->pharmacist,
        );

        Livewire::actingAs($this->pharmacist)
            ->test(BarcodeDispense::class)
            ->set('barcode', 'TEST-BC-002')
            ->call('scanBarcode')
            ->set('quantity', 10)
            ->call('dispense')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'quantity'          => -10,
            'reference_type'    => 'manual_dispense',
        ]);
    }
}
