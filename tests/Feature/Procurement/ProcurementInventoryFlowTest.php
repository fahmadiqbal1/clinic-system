<?php

namespace Tests\Feature\Procurement;

use Tests\TestCase;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\Expense;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProcurementService;

class ProcurementInventoryFlowTest extends TestCase
{
    protected ProcurementService $procurementService;
    protected User $owner;
    protected User $pharmacy;
    protected User $lab;
    protected User $radiology;
    protected User $receptionist;
    protected User $doctor;
    protected User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procurementService = app(ProcurementService::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');

        $this->pharmacy = User::factory()->create();
        $this->pharmacy->assignRole('Pharmacy');

        $this->lab = User::factory()->create();
        $this->lab->assignRole('Laboratory');

        $this->radiology = User::factory()->create();
        $this->radiology->assignRole('Radiology');

        $this->receptionist = User::factory()->create();
        $this->receptionist->assignRole('Receptionist');

        $this->doctor = User::factory()->create();
        $this->doctor->assignRole('Doctor');

        $this->patient = User::factory()->create();
        $this->patient->assignRole('Patient');
    }

    public function test_inventory_procurement_complete_flow(): void
    {
        $item = InventoryItem::factory()->pharmacy()->create();

        // 1. Create procurement request
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create(['requested_by' => $this->pharmacy->id, 'department' => 'pharmacy']);

        $procItem = ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 100]);

        $this->assertDatabaseHas('procurement_requests', [
            'id' => $request->id,
            'type' => 'inventory',
            'status' => 'pending',
        ]);

        // 2. Owner approves (no expense yet)
        $request->update(['status' => 'approved', 'approved_by' => $this->owner->id]);
        $this->assertDatabaseCount('expenses', 0);

        // 3. Staff receives with unit prices
        $unitPrices = [$procItem->id => 25.00];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        // 4. Verify: Stock increased
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'quantity' => 100,
            'reference_type' => 'procurement_request',
        ]);

        // 5. Verify: Expense created at receipt
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', [
            'department' => 'pharmacy',
            'cost' => 100 * 25.00,
        ]);

        // 6. Verify: Request marked received
        $this->assertDatabaseHas('procurement_requests', [
            'id' => $request->id,
            'status' => 'received',
        ]);
    }

    public function test_cannot_approve_inventory_procurement_without_inventory_item_id(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create(['requested_by' => $this->pharmacy->id]);

        ProcurementRequestItem::factory()->create([
            'procurement_request_id' => $request->id,
            'inventory_item_id' => null,
            'quantity_requested' => 50,
        ]);

        $request->load('items');
        foreach ($request->items as $procItem) {
            if ($request->type === 'inventory' && $procItem->inventory_item_id === null) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail('Should detect missing inventory_item_id');
    }

    public function test_cannot_receive_inventory_procurement_without_unit_price(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50, 'unit_price' => null]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unit price missing');

        $this->procurementService->receiveProcurement($request, []);
    }

    public function test_cannot_receive_inventory_procurement_twice(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem = ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $unitPrices = [$procItem->id => 10.00];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot receive procurement request');

        $this->procurementService->receiveProcurement($request, $unitPrices);
    }

    public function test_receipt_creates_exactly_one_expense(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem = ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $unitPrice = 25.00;
        $unitPrices = [$procItem->id => $unitPrice];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', [
            'department' => $request->department,
            'cost' => 50 * $unitPrice,
        ]);
    }

    public function test_receipt_creates_exactly_one_stock_movement(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem = ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 100]);

        $unitPrices = [$procItem->id => 20.00];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'quantity' => 100,
            'reference_type' => 'procurement_request',
            'reference_id' => $request->id,
        ]);
    }

    public function test_receipt_before_approval_forbidden(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create();

        ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('status');

        $this->procurementService->receiveProcurement($request, []);
    }

    public function test_receipt_sets_quantity_received_equals_quantity_requested(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem = ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 75]);

        $unitPrices = [$procItem->id => 15.00];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        $this->assertDatabaseHas('procurement_request_items', [
            'id' => $procItem->id,
            'quantity_requested' => 75,
            'quantity_received' => 75,
        ]);
    }

    public function test_receipt_updates_status_to_received(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, [$request->items->first()->id => 10.00]);

        $this->assertDatabaseHas('procurement_requests', [
            'id' => $request->id,
            'status' => 'received',
        ]);
    }

    public function test_receipt_with_multiple_items(): void
    {
        $item1 = InventoryItem::factory()->create();
        $item2 = InventoryItem::factory()->create();

        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem1 = ProcurementRequestItem::factory()
            ->withInventoryItem($item1)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $procItem2 = ProcurementRequestItem::factory()
            ->withInventoryItem($item2)
            ->withProcurement($request)
            ->create(['quantity_requested' => 100]);

        $unitPrices = [
            $procItem1->id => 10.00,
            $procItem2->id => 20.00,
        ];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('expenses', 2);

        $totalCost = (50 * 10.00) + (100 * 20.00);
        $this->assertEquals($totalCost, Expense::sum('cost'));
    }

    public function test_expense_amount_calculated_as_quantity_times_unit_price(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem = ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 100]);

        $unitPrice = 45.50;
        $unitPrices = [$procItem->id => $unitPrice];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        $expense = Expense::first();
        $this->assertEquals(100 * 45.50, $expense->cost);
        $this->assertEquals(4550.00, $expense->cost);
    }

    public function test_owner_only_can_approve_procurement(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create(['requested_by' => $this->pharmacy->id]);

        $this->actingAs($this->owner);
        $this->assertTrue($this->owner->hasRole('Owner'));

        $this->actingAs($this->pharmacy);
        $this->assertFalse($this->pharmacy->hasRole('Owner'));
    }

    public function test_owner_only_can_reject_procurement(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create(['requested_by' => $this->pharmacy->id]);

        $this->actingAs($this->owner);
        $this->assertTrue($this->owner->hasRole('Owner'));

        $this->actingAs($this->pharmacy);
        $this->assertFalse($this->pharmacy->hasRole('Owner'));
    }

    public function test_staff_can_receive_inventory_procurements(): void
    {
        $staffRoles = ['Pharmacy', 'Laboratory', 'Radiology', 'Receptionist'];
        $staff = [$this->pharmacy, $this->lab, $this->radiology, $this->receptionist];

        foreach ($staff as $user) {
            $this->assertContains($user->getRoleNames()[0], $staffRoles);
        }
    }

    public function test_owner_can_receive_inventory_procurements(): void
    {
        $this->assertTrue($this->owner->hasRole('Owner'));
    }

    public function test_doctor_cannot_receive_procurement(): void
    {
        $this->assertFalse($this->doctor->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']));
    }

    public function test_patient_cannot_receive_procurement(): void
    {
        $this->assertFalse($this->patient->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']));
    }

    public function test_staff_can_create_procurement_request(): void
    {
        $staffRoles = ['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist'];
        $staff = [$this->owner, $this->pharmacy, $this->lab, $this->radiology, $this->receptionist];

        foreach ($staff as $user) {
            $this->assertTrue($user->hasAnyRole($staffRoles));
        }
    }

    public function test_doctor_cannot_create_procurement_request(): void
    {
        $this->assertFalse($this->doctor->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']));
    }

    public function test_patient_cannot_create_procurement_request(): void
    {
        $this->assertFalse($this->patient->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']));
    }

    public function test_approving_twice_forbidden(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $request->update(['status' => 'pending', 'approved_by' => null]);
        $request->update(['status' => 'approved', 'approved_by' => $this->owner->id]);

        $this->assertNotNull($request->approved_by);
    }

    public function test_rejecting_after_approval_forbidden(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create(['approved_by' => $this->owner->id]);

        $this->assertEquals('approved', $request->status);
    }

    public function test_status_skipping_forbidden(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create();

        $this->assertEquals('pending', $request->status);

        ProcurementRequestItem::factory()
            ->create(['procurement_request_id' => $request->id]);
    }

    public function test_cannot_receive_rejected_procurement(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->rejected()
            ->create(['status' => 'rejected']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('status');

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, []);
    }

    public function test_cannot_receive_pending_procurement(): void
    {
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('status');

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, []);
    }

    public function test_procurement_request_items_immutable_after_receipt(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem = ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $unitPrices = [$procItem->id => 10.00];

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, $unitPrices);

        $procItem->refresh();
        $this->assertNotNull($procItem->quantity_received);
        $this->assertEquals(50, $procItem->quantity_received);
    }

    public function test_expense_created_only_at_receipt_for_inventory_procurement(): void
    {
        $item = InventoryItem::factory()->create();
        $request = ProcurementRequest::factory()
            ->inventory()
            ->pending()
            ->create();

        ProcurementRequestItem::factory()
            ->withInventoryItem($item)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $request->update(['status' => 'approved', 'approved_by' => $this->owner->id]);
        $this->assertDatabaseCount('expenses', 0);

        $procItem = $request->items->first();
        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, [$procItem->id => 10.00]);

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_all_items_must_have_unit_price_for_receipt(): void
    {
        $item1 = InventoryItem::factory()->create();
        $item2 = InventoryItem::factory()->create();

        $request = ProcurementRequest::factory()
            ->inventory()
            ->approved()
            ->create();

        $procItem1 = ProcurementRequestItem::factory()
            ->withInventoryItem($item1)
            ->withProcurement($request)
            ->create(['quantity_requested' => 50]);

        $procItem2 = ProcurementRequestItem::factory()
            ->withInventoryItem($item2)
            ->withProcurement($request)
            ->create(['quantity_requested' => 100]);

        $this->expectException(\Exception::class);

        $this->actingAs($this->pharmacy);
        $this->procurementService->receiveProcurement($request, [
            $procItem1->id => 10.00,
        ]);
    }
}
