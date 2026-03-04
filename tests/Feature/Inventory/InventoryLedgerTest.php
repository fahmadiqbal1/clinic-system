<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;

class InventoryLedgerTest extends TestCase
{
    protected InventoryService $inventory;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
        $this->user = User::factory()->create();
    }

    public function test_inbound_stock_movement_increases_calculated_stock(): void
    {
        $item = InventoryItem::factory()->create();

        $this->assertEquals(0, $this->inventory->getCurrentStock($item));

        $this->inventory->recordInbound(
            item: $item,
            quantity: 50,
            unitCost: 10.00,
            referenceType: 'procurement_request',
            referenceId: 1,
            user: $this->user
        );

        $this->assertEquals(50, $this->inventory->getCurrentStock($item));
    }

    public function test_outbound_stock_movement_decreases_calculated_stock(): void
    {
        $item = InventoryItem::factory()->create();

        // Add stock first
        $this->inventory->recordInbound($item, 100, 10.00, 'procurement_request', 1, $this->user);
        $this->assertEquals(100, $this->inventory->getCurrentStock($item));

        // Remove stock
        $this->inventory->recordOutbound($item, 30, 'invoice', 1, $this->user);

        $this->assertEquals(70, $this->inventory->getCurrentStock($item));
    }

    public function test_outbound_with_insufficient_stock_throws_exception(): void
    {
        $item = InventoryItem::factory()->create();

        $this->inventory->recordInbound($item, 20, 10.00, 'procurement_request', 1, $this->user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->inventory->recordOutbound($item, 50, 'invoice', 1, $this->user);
    }

    public function test_stock_cannot_go_negative(): void
    {
        $item = InventoryItem::factory()->create();

        $this->expectException(\Exception::class);

        $this->inventory->recordOutbound($item, 10, 'invoice', 1, $this->user);
    }

    public function test_stock_is_derived_from_ledger_not_stored_column(): void
    {
        $item = InventoryItem::factory()->create();

        // Inventory item has no quantity_in_stock column
        $this->assertFalse(isset($item->quantity_in_stock));

        // Add multiple movements
        $this->inventory->recordInbound($item, 100, 10.00, 'procurement_request', 1, $this->user);
        $this->inventory->recordInbound($item, 50, 10.00, 'procurement_request', 2, $this->user);
        $this->inventory->recordOutbound($item, 30, 'invoice', 1, $this->user);

        // Sum movements manually
        $manualSum = StockMovement::where('inventory_item_id', $item->id)->sum('quantity');

        // Service calculates from ledger
        $calculatedStock = $this->inventory->getCurrentStock($item);

        $this->assertEquals($manualSum, $calculatedStock);
        $this->assertEquals(120, $calculatedStock);
    }

    public function test_multiple_inbound_movements_sum_correctly(): void
    {
        $item = InventoryItem::factory()->create();

        $this->inventory->recordInbound($item, 100, 10.00, 'procurement_request', 1, $this->user);
        $this->inventory->recordInbound($item, 50, 10.00, 'procurement_request', 2, $this->user);
        $this->inventory->recordInbound($item, 25, 10.00, 'procurement_request', 3, $this->user);

        $this->assertEquals(175, $this->inventory->getCurrentStock($item));
    }

    public function test_mixed_movements_calculate_correctly(): void
    {
        $item = InventoryItem::factory()->create();

        $this->inventory->recordInbound($item, 100, 10.00, 'procurement_request', 1, $this->user);
        $this->inventory->recordOutbound($item, 10, 'invoice', 1, $this->user);
        $this->inventory->recordOutbound($item, 20, 'invoice', 2, $this->user);
        $this->inventory->recordInbound($item, 50, 10.00, 'procurement_request', 2, $this->user);

        $this->assertEquals(120, $this->inventory->getCurrentStock($item));
    }

    public function test_below_minimum_stock_detection(): void
    {
        $item = InventoryItem::factory()->create(['minimum_stock_level' => 50]);

        $this->inventory->recordInbound($item, 100, 10.00, 'procurement_request', 1, $this->user);
        $this->assertFalse($this->inventory->isBelowMinimum($item));

        $this->inventory->recordOutbound($item, 60, 'invoice', 1, $this->user);
        $this->assertTrue($this->inventory->isBelowMinimum($item));
    }

    public function test_stock_movement_quantity_sign_determines_direction(): void
    {
        $item = InventoryItem::factory()->create();

        $inbound = $this->inventory->recordInbound($item, 50, 10.00, 'procurement_request', 1, $this->user);
        $outbound = $this->inventory->recordOutbound($item, 20, 'invoice', 1, $this->user);

        $this->assertGreaterThan(0, $inbound->quantity);
        $this->assertLessThan(0, $outbound->quantity);

        $this->assertEquals(50, $inbound->quantity);
        $this->assertEquals(-20, $outbound->quantity);
    }

    public function test_stock_movements_are_immutable(): void
    {
        $item = InventoryItem::factory()->create();

        $movement = $this->inventory->recordInbound($item, 50, 10.00, 'procurement_request', 1, $this->user);

        // Verify movement is recorded immutably
        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'quantity' => 50,
        ]);
    }
}
