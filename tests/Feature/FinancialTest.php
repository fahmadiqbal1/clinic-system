<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\RevenueLedger;
use App\Models\User;
use App\Services\FinancialDistributionService;
use Tests\TestCase;

class FinancialTest extends TestCase
{
    private FinancialDistributionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FinancialDistributionService();
    }

    /** Helper: create a paid invoice ready for distribution */
    private function createPaidInvoice(array $overrides = []): Invoice
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $defaults = [
            'status' => 'pending',
            'total_amount' => 1000,
        ];

        $merged = array_merge($defaults, $overrides);

        // Auto-set performed_by_user_id for consultation from prescribing_doctor_id
        $department = $merged['department'] ?? 'consultation';
        if ($department === 'consultation' && !isset($merged['performed_by_user_id']) && isset($merged['prescribing_doctor_id'])) {
            $merged['performed_by_user_id'] = $merged['prescribing_doctor_id'];
        }

        $invoice = Invoice::factory()->clinic()->create($merged);

        // Walk through state machine: pending → in_progress → completed → paid
        $invoice->startWork();
        $invoice->markCompleted();

        // Pharmacy invoices are marked paid by pharmacist; others by receptionist
        if ($department === 'pharmacy') {
            $pharmacist = User::factory()->create();
            $pharmacist->assignRole('Pharmacy');
            $invoice->markPaid('cash', $pharmacist->id);
        } else {
            $invoice->markPaid('cash', $receptionist->id);
        }

        return $invoice->fresh();
    }

    public function test_consultation_distribution_with_doctor(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 1000,
        ]);

        $ledgers = RevenueLedger::where('invoice_id', $invoice->id)->get();

        // Should have: Revenue credit + Doctor debit + Owner debit
        $credits = $ledgers->where('entry_type', 'credit');
        $debits = $ledgers->where('entry_type', 'debit');

        $this->assertEquals(1, $credits->count());
        $this->assertEquals(1000, (float) $credits->first()->amount);

        $doctorEntry = $debits->where('role_type', 'Doctor')->first();
        $this->assertNotNull($doctorEntry);
        $this->assertEquals(70.00, (float) $doctorEntry->percentage);
        $this->assertEquals(700.00, (float) $doctorEntry->amount);
        $this->assertEquals($doctor->id, $doctorEntry->user_id);

        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertNotNull($ownerEntry);
        $this->assertEquals(30.00, (float) $ownerEntry->percentage);
        $this->assertEquals(300.00, (float) $ownerEntry->amount);

        // Balanced: credits = debits
        $this->assertEqualsWithDelta(
            $credits->sum('amount'),
            $debits->sum('amount'),
            0.01
        );
    }

    public function test_consultation_per_user_override(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 80.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 1000,
        ]);

        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        $doctorEntry = $debits->where('role_type', 'Doctor')->first();
        $this->assertEquals(80.00, (float) $doctorEntry->percentage);
        $this->assertEquals(800.00, (float) $doctorEntry->amount);

        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertEquals(20.00, (float) $ownerEntry->percentage);
        $this->assertEquals(200.00, (float) $ownerEntry->amount);
    }

    public function test_lab_distribution_with_technician(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_lab' => 0,
        ]);
        $techUser = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_lab' => 55.00,
        ]);
        $techUser->assignRole('Laboratory');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'lab',
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $techUser->id,
            'total_amount' => 1000,
        ]);

        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        $techEntry = $debits->where('role_type', 'Technician')->first();
        $this->assertNotNull($techEntry);
        $this->assertEquals(55.00, (float) $techEntry->percentage);
        $this->assertEquals(550.00, (float) $techEntry->amount);

        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertNotNull($ownerEntry);
        $this->assertEquals(45.00, (float) $ownerEntry->percentage);
        $this->assertEquals(450.00, (float) $ownerEntry->amount);
    }

    public function test_lab_with_per_user_technician(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_lab' => 0,
        ]);
        $techUser = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_lab' => 55.00,
        ]);
        $techUser->assignRole('Laboratory');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'lab',
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $techUser->id,
            'total_amount' => 1000,
        ]);

        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        $techEntry = $debits->where('role_type', 'Technician')->first();
        $this->assertNotNull($techEntry);
        $this->assertEquals(55.00, (float) $techEntry->percentage);
        $this->assertEquals(550.00, (float) $techEntry->amount);
        $this->assertEquals($techUser->id, $techEntry->user_id);

        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertEquals(45.00, (float) $ownerEntry->percentage);
        $this->assertEquals(450.00, (float) $ownerEntry->amount);
    }

    public function test_radiology_with_referrer(): void
    {
        $techUser = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_radiology' => 55.00,
        ]);
        $techUser->assignRole('Radiology');

        $invoice = $this->createPaidInvoice([
            'patient_type' => 'walk_in',
            'patient_id' => null,
            'department' => 'radiology',
            'prescribing_doctor_id' => null,
            'performed_by_user_id' => $techUser->id,
            'referrer_name' => 'Dr. External',
            'referrer_percentage' => 10.00,
            'total_amount' => 2000,
        ]);

        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        $referrerEntry = $debits->where('role_type', 'Referrer')->first();
        $this->assertNotNull($referrerEntry);
        $this->assertEquals(10.00, (float) $referrerEntry->percentage);
        $this->assertEquals(200.00, (float) $referrerEntry->amount);

        $techEntry = $debits->where('role_type', 'Technician')->first();
        $this->assertNotNull($techEntry);
        $this->assertEquals(55.00, (float) $techEntry->percentage);
        $this->assertEquals(1100.00, (float) $techEntry->amount);

        // Owner absorbs remainder: 100% - 10% referrer - 55% technician = 35%
        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertNotNull($ownerEntry);
        $this->assertEquals(35.00, (float) $ownerEntry->percentage);
        $this->assertEquals(700.00, (float) $ownerEntry->amount);
    }

    public function test_pharmacy_profit_based_distribution(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_pharmacy' => 15.00,
        ]);
        $doctor->assignRole('Doctor');
        $pharmacist = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_pharmacy' => 35.00,
        ]);
        $pharmacist->assignRole('Pharmacy');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        // Create paid pharmacy invoice with items
        $invoice = Invoice::factory()->clinic()->pharmacy()->create([
            'patient_id' => $patient->id,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $pharmacist->id,
            'total_amount' => 1000,
            'status' => 'pending',
        ]);

        // Add item with COGS
        $item = InventoryItem::factory()->create([
            'department' => 'pharmacy',
            'selling_price' => 100,
            'weighted_avg_cost' => 60,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'inventory_item_id' => $item->id,
            'description' => 'Test medicine',
            'quantity' => 10,
            'unit_price' => 100,
            'cost_price' => 60,
            'line_total' => 1000,
            'line_cogs' => 600.00,
        ]);

        $invoice->startWork();
        $invoice->markCompleted();
        $invoice->markPaid('cash', $pharmacist->id);
        $invoice = $invoice->fresh();

        // Revenue=1000, COGS=10*60=600, Profit=400
        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        $cogsEntry = $debits->where('category', 'cogs')->first();
        $this->assertNotNull($cogsEntry);
        $this->assertEquals(600.00, (float) $cogsEntry->amount);

        // Doctor: 15% of 400 profit = 60
        $doctorEntry = $debits->where('role_type', 'Doctor')->first();
        $this->assertNotNull($doctorEntry);
        $this->assertEquals(60.00, (float) $doctorEntry->amount);

        // Pharmacist: 35% of 400 profit = 140
        $pharmacistEntry = $debits->where('role_type', 'Pharmacist')->first();
        $this->assertNotNull($pharmacistEntry);
        $this->assertEquals(140.00, (float) $pharmacistEntry->amount);

        // Owner: 50% of 400 profit = 200
        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertNotNull($ownerEntry);
        $this->assertEquals(200.00, (float) $ownerEntry->amount);

        // Balanced
        $credits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'credit')->sum('amount');
        $totalDebits = $debits->sum('amount');
        $this->assertEqualsWithDelta((float) $credits, (float) $totalDebits, 0.01);
    }

    public function test_pharmacy_zero_profit_no_commission(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_pharmacy' => 15.00,
        ]);
        $doctor->assignRole('Doctor');
        $pharmacist = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_pharmacy' => 35.00,
        ]);
        $pharmacist->assignRole('Pharmacy');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = Invoice::factory()->clinic()->pharmacy()->create([
            'patient_id' => $patient->id,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $pharmacist->id,
            'total_amount' => 600,
            'status' => 'pending',
        ]);

        // COGS = revenue → zero profit
        $item = InventoryItem::factory()->create([
            'department' => 'pharmacy',
            'selling_price' => 60,
            'weighted_avg_cost' => 60,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'inventory_item_id' => $item->id,
            'description' => 'Medicine at cost',
            'quantity' => 10,
            'unit_price' => 60,
            'cost_price' => 60,
            'line_total' => 600,
            'line_cogs' => 600.00,
        ]);

        $invoice->startWork();
        $invoice->markCompleted();
        $invoice->markPaid('cash', $pharmacist->id);

        $debits = RevenueLedger::where('invoice_id', $invoice->fresh()->id)
            ->where('entry_type', 'debit')->get();

        // Only COGS debit, no commission entries
        $commissions = $debits->where('category', 'commission');
        $this->assertCount(0, $commissions);

        $cogsEntry = $debits->where('category', 'cogs')->first();
        $this->assertNotNull($cogsEntry);
        $this->assertEquals(600.00, (float) $cogsEntry->amount);
    }

    public function test_idempotency_prevents_double_distribution(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 1000,
        ]);

        // Try distributing again — should be idempotent
        $this->service->distribute($invoice);

        $count = RevenueLedger::where('invoice_id', $invoice->id)->count();
        // Revenue credit + Doctor debit + Owner debit = 3
        $this->assertEquals(3, $count);
    }

    public function test_unpaid_invoice_throws_exception(): void
    {
        $invoice = Invoice::factory()->create([
            'total_amount' => 1000,
            'status' => 'pending',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commission distribution requires a fully paid invoice.');
        $this->service->distribute($invoice);
    }

    public function test_discount_reduces_effective_revenue(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $invoice = Invoice::factory()->clinic()->consultation()->create([
            'patient_id' => $patient->id,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $doctor->id,
            'total_amount' => 1000,
            'discount_amount' => 200,
            'status' => 'pending',
        ]);

        $invoice->startWork();
        $invoice->markCompleted();
        $invoice->markPaid('cash', $receptionist->id);
        $invoice = $invoice->fresh();

        // Net = 1000 - 200 = 800
        $this->assertEquals(800.00, (float) $invoice->net_amount);

        $credits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'credit')->first();
        $this->assertEquals(800.00, (float) $credits->amount);

        // Doctor: 70% of 800 = 560
        $doctorEntry = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('role_type', 'Doctor')->first();
        $this->assertEquals(560.00, (float) $doctorEntry->amount);
    }

    public function test_balanced_ledger_credits_equal_debits(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 3333,
        ]);

        $credits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'credit')->sum('amount');
        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->sum('amount');

        $this->assertEqualsWithDelta((float) $credits, (float) $debits, 0.01);
    }

    public function test_misconfigured_percentages_throws_exception(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        // Default doctor is 70%. Add external referrer at 40%.
        // Total = 70% + 40% = 110% > 100% → exception

        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $invoice = Invoice::factory()->clinic()->consultation()->create([
            'patient_id' => $patient->id,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $doctor->id,
            'referrer_name' => 'Dr. External',
            'referrer_percentage' => 40.00,
            'total_amount' => 1000,
            'status' => 'pending',
        ]);

        $invoice->startWork();
        $invoice->markCompleted();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('percentages exceed 100%');
        $invoice->markPaid('cash', $receptionist->id);
    }

    public function test_distribution_snapshot_stored_at_payment(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $doctor->id,
            'total_amount' => 1000,
        ]);

        $invoice->refresh();
        $this->assertNotNull($invoice->distribution_snapshot);
        $snapshot = $invoice->distribution_snapshot;
        $this->assertArrayHasKey('frozen_at', $snapshot);
        $this->assertArrayHasKey('effective_revenue', $snapshot);
        $this->assertArrayHasKey('entries', $snapshot);
        $this->assertArrayHasKey('engine_version', $snapshot);
        $this->assertEquals('2.0-actor-based', $snapshot['engine_version']);
        $this->assertEquals(1000.00, $snapshot['effective_revenue']);
        $this->assertEquals($doctor->id, $snapshot['performed_by_user_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Actor-Based Distribution Tests
    |--------------------------------------------------------------------------
    */

    public function test_salaried_performer_gets_no_commission(): void
    {
        $doctor = User::factory()->create(['compensation_type' => 'salaried']);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $doctor->id,
            'total_amount' => 1000,
        ]);

        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        // Salaried doctor gets NO commission
        $doctorEntry = $debits->where('role_type', 'Doctor')->first();
        $this->assertNull($doctorEntry);

        // Owner absorbs 100% of profit
        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertNotNull($ownerEntry);
        $this->assertEquals(100.00, (float) $ownerEntry->percentage);
        $this->assertEquals(1000.00, (float) $ownerEntry->amount);
    }

    public function test_hybrid_performer_gets_commission(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'hybrid',
            'base_salary' => 5000,
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $doctor->id,
            'total_amount' => 1000,
        ]);

        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        // Hybrid doctor still gets commission (base_salary is separate expense, not deducted here)
        $doctorEntry = $debits->where('role_type', 'Doctor')->first();
        $this->assertNotNull($doctorEntry);
        $this->assertEquals(70.00, (float) $doctorEntry->percentage);
        $this->assertEquals(700.00, (float) $doctorEntry->amount);
    }

    public function test_salaried_doctor_referral_skipped(): void
    {
        $salariedDoctor = User::factory()->create(['compensation_type' => 'salaried']);
        $salariedDoctor->assignRole('Doctor');
        $pharmacist = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_pharmacy' => 35.00,
        ]);
        $pharmacist->assignRole('Pharmacy');
        $patient = Patient::factory()->create(['doctor_id' => $salariedDoctor->id]);

        $invoice = Invoice::factory()->clinic()->pharmacy()->create([
            'patient_id' => $patient->id,
            'prescribing_doctor_id' => $salariedDoctor->id,
            'performed_by_user_id' => $pharmacist->id,
            'total_amount' => 1000,
            'status' => 'pending',
        ]);

        $item = InventoryItem::factory()->create([
            'department' => 'pharmacy',
            'selling_price' => 100,
            'weighted_avg_cost' => 60,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'inventory_item_id' => $item->id,
            'description' => 'Test medicine',
            'quantity' => 10,
            'unit_price' => 100,
            'cost_price' => 60,
            'line_total' => 1000,
            'line_cogs' => 600.00,
        ]);

        $invoice->startWork();
        $invoice->markCompleted();
        $invoice->markPaid('cash', $pharmacist->id);
        $invoice = $invoice->fresh();

        $debits = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('entry_type', 'debit')->get();

        // Salaried doctor gets NO referral commission
        $doctorEntry = $debits->where('role_type', 'Doctor')->first();
        $this->assertNull($doctorEntry);

        // Pharmacist still gets 35% of 400 profit = 140
        $pharmacistEntry = $debits->where('role_type', 'Pharmacist')->first();
        $this->assertNotNull($pharmacistEntry);
        $this->assertEquals(140.00, (float) $pharmacistEntry->amount);

        // Owner absorbs 65% of 400 profit = 260
        $ownerEntry = $debits->where('role_type', 'Owner')->first();
        $this->assertNotNull($ownerEntry);
        $this->assertEquals(260.00, (float) $ownerEntry->amount);
    }

    public function test_pharmacy_mark_paid_by_pharmacist(): void
    {
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('Pharmacy');
        $patient = Patient::factory()->create();

        $invoice = Invoice::factory()->clinic()->pharmacy()->create([
            'patient_id' => $patient->id,
            'performed_by_user_id' => $pharmacist->id,
            'total_amount' => 500,
            'status' => 'pending',
        ]);

        $invoice->startWork();
        $invoice->markCompleted();

        // Pharmacist can mark pharmacy invoices paid
        $result = $invoice->markPaid('cash', $pharmacist->id);
        $this->assertTrue($result);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_pharmacist_cannot_mark_consultation_paid(): void
    {
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('Pharmacy');
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = Invoice::factory()->clinic()->consultation()->create([
            'patient_id' => $patient->id,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $doctor->id,
            'total_amount' => 500,
            'status' => 'pending',
        ]);

        $invoice->startWork();
        $invoice->markCompleted();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Receptionist or Owner');
        $invoice->markPaid('cash', $pharmacist->id);
    }

    public function test_discount_workflow_request_approve(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $invoice = Invoice::factory()->create([
            'total_amount' => 1000,
            'status' => 'pending',
        ]);

        // Staff requests discount
        $invoice->requestDiscount(200, $receptionist->id, 'Loyal customer');
        $invoice->refresh();

        $this->assertEquals(Invoice::DISCOUNT_PENDING, $invoice->discount_status);
        $this->assertEquals(200.00, (float) $invoice->discount_amount);
        $this->assertEquals($receptionist->id, $invoice->discount_requested_by);

        // Owner approves
        $invoice->approveDiscount($owner->id);
        $invoice->refresh();

        $this->assertEquals(Invoice::DISCOUNT_APPROVED, $invoice->discount_status);
        $this->assertEquals($owner->id, $invoice->discount_approved_by);
        $this->assertNotNull($invoice->discount_approved_at);
    }

    public function test_discount_workflow_request_reject(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $invoice = Invoice::factory()->create([
            'total_amount' => 1000,
            'status' => 'pending',
        ]);

        $invoice->requestDiscount(500, $receptionist->id, 'Too much');
        $invoice->refresh();

        // Owner rejects
        $invoice->rejectDiscount($owner->id, 'Too high');
        $invoice->refresh();

        $this->assertEquals(Invoice::DISCOUNT_REJECTED, $invoice->discount_status);
        $this->assertEquals(0, (float) $invoice->discount_amount);
        $this->assertEquals($owner->id, $invoice->discount_approved_by);
    }

    public function test_pending_discount_blocks_payment(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
        ]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = Invoice::factory()->clinic()->consultation()->create([
            'patient_id' => $patient->id,
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $doctor->id,
            'total_amount' => 1000,
            'status' => 'pending',
        ]);

        $invoice->startWork();
        $invoice->markCompleted();

        // Request a discount
        $invoice->requestDiscount(200, $receptionist->id, 'Please discount');

        // Cannot mark paid while pending
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot mark invoice as paid while discount is pending approval.');
        $invoice->markPaid('cash', $receptionist->id);
    }

    public function test_all_departments_use_profit_based_distribution(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_lab' => 10.00,
        ]);
        $doctor->assignRole('Doctor');
        $techUser = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_lab' => 55.00,
        ]);
        $techUser->assignRole('Laboratory');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        // Lab with no COGS → profit = revenue
        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'lab',
            'prescribing_doctor_id' => $doctor->id,
            'performed_by_user_id' => $techUser->id,
            'total_amount' => 1000,
        ]);

        $snapshot = $invoice->distribution_snapshot;
        $this->assertEquals(0, $snapshot['total_cogs']);
        $this->assertEquals(1000.00, $snapshot['profit']);
    }

    public function test_per_user_commission_rates_used_correctly(): void
    {
        $user = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_pharmacy' => 35.00,
            'commission_lab' => 55.00,
        ]);

        // commissionRateFor should return correct rate per department
        $this->assertEquals(35.00, $user->commissionRateFor('pharmacy'));
        $this->assertEquals(55.00, $user->commissionRateFor('lab'));
        $this->assertEquals(0, $user->commissionRateFor('consultation'));
        $this->assertEquals(0, $user->commissionRateFor('radiology'));
    }
}
