<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\RevenueLedger;
use App\Models\User;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{

    /** Helper to create a fully paid invoice via state machine */
    private function createPaidInvoice(array $overrides = []): Invoice
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $merged = array_merge(['status' => 'pending', 'total_amount' => 1000], $overrides);

        // Auto-set performed_by_user_id for consultation
        $department = $merged['department'] ?? 'consultation';
        if ($department === 'consultation' && !isset($merged['performed_by_user_id']) && isset($merged['prescribing_doctor_id'])) {
            $merged['performed_by_user_id'] = $merged['prescribing_doctor_id'];
        }

        $invoice = Invoice::factory()->clinic()->create($merged);

        $invoice->startWork();
        $invoice->markCompleted();

        if ($department === 'pharmacy') {
            $pharmacist = User::factory()->create();
            $pharmacist->assignRole('Pharmacy');
            $invoice->markPaid('cash', $pharmacist->id);
        } else {
            $invoice->markPaid('cash', $receptionist->id);
        }

        return $invoice->fresh();
    }

    public function test_owner_can_access_financial_report(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->get(route('owner.financial-report'));

        $response->assertStatus(200);
        $response->assertViewHas('revenue');
    }

    public function test_non_owner_cannot_access_financial_report(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create(['compensation_type' => 'commission', 'commission_consultation' => 70.00]);
        $doctor->assignRole('Doctor');

        $response = $this->actingAs($doctor)->get(route('owner.financial-report'));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_financial_report(): void
    {
        $response = $this->get(route('owner.financial-report'));

        $response->assertRedirect(route('login'));
    }

    public function test_revenue_calculation_correct(): void
    {
        $today = Carbon::today()->startOfDay();

        $doctor = User::factory()->create(['compensation_type' => 'commission', 'commission_consultation' => 70.00]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 1000,
            'created_at' => $today,
        ]);

        // Gross revenue from credit entry = 1000
        $financialService = new FinancialReportService();
        $revenue = $financialService->getRevenueBetween($today, $today->copy()->endOfDay());

        $this->assertEquals(1000, $revenue);
    }

    public function test_expense_calculation_correct(): void
    {
        $today = Carbon::today()->startOfDay();
        $creator = User::factory()->create();

        Expense::create([
            'department' => 'lab',
            'description' => 'Lab supplies',
            'cost' => 500,
            'created_by' => $creator->id,
            'created_at' => $today,
        ]);

        Expense::create([
            'department' => 'lab',
            'description' => 'Lab maintenance',
            'cost' => 300,
            'created_by' => $creator->id,
            'created_at' => $today,
        ]);

        $financialService = new FinancialReportService();
        $expenses = $financialService->getExpensesBetween($today, $today->copy()->endOfDay());

        $this->assertEquals(800, $expenses);
    }

    public function test_net_profit_calculation_correct(): void
    {
        $today = Carbon::today()->startOfDay();
        $creator = User::factory()->create();

        $doctor = User::factory()->create(['compensation_type' => 'commission', 'commission_consultation' => 70.00]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 1000,
            'created_at' => $today,
        ]);

        Expense::create([
            'department' => 'consultation',
            'description' => 'Consultation room rent',
            'cost' => 100,
            'created_by' => $creator->id,
            'created_at' => $today,
        ]);

        $financialService = new FinancialReportService();
        $revenue = $financialService->getRevenueBetween($today, $today->copy()->endOfDay());
        $expenses = $financialService->getExpensesBetween($today, $today->copy()->endOfDay());
        $netProfit = $financialService->getNetProfitBetween($today, $today->copy()->endOfDay());

        // Gross revenue = 1000, expenses = 100, doctor commission = 700 (70%), COGS = 0
        // Net profit (owner) = revenue - expenses - commissions - cogs = 1000 - 100 - 700 - 0 = 200
        $this->assertEquals(1000, $revenue);
        $this->assertEquals(100, $expenses);
        $this->assertEquals(200, $netProfit);
    }

    public function test_payout_marking_works(): void
    {
        $owner = User::factory()->create();
        $doctor = User::factory()->create(['compensation_type' => 'commission', 'commission_consultation' => 70.00]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $invoice = $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 1000,
        ]);

        // Check doctor ledger entry
        $ledger = RevenueLedger::where('invoice_id', $invoice->id)
            ->where('role_type', 'Doctor')->first();
        $this->assertNotNull($ledger);
        $this->assertEquals('pending', $ledger->payout_status);
        $this->assertNull($ledger->paid_at);

        // Mark as paid
        $now = Carbon::now();
        $ledger->update([
            'payout_status' => 'paid',
            'paid_at' => $now,
            'paid_by' => $owner->id,
        ]);

        $ledger->refresh();
        $this->assertEquals('paid', $ledger->payout_status);
        $this->assertNotNull($ledger->paid_at);
        $this->assertEquals($owner->id, $ledger->paid_by);
    }

    public function test_pending_payouts_calculation(): void
    {
        $doctor = User::factory()->create(['compensation_type' => 'commission', 'commission_consultation' => 70.00]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        for ($i = 0; $i < 3; $i++) {
            $this->createPaidInvoice([
                'patient_id' => $patient->id,
                'department' => 'consultation',
                'prescribing_doctor_id' => $doctor->id,
                'total_amount' => 1000,
            ]);
        }

        $financialService = new FinancialReportService();
        $pendingPayouts = $financialService->getPendingPayouts();

        // Each: revenue credit (1000) + doctor debit (700) + owner debit (300)
        // getPendingPayouts() sums commission category entries only (not owner_remainder)
        // 3 invoices × 700 doctor commission = 2100
        $this->assertEquals(2100, $pendingPayouts);
    }

    public function test_financial_report_view_displays_data(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $today = Carbon::today()->startOfDay();
        $creator = User::factory()->create();

        $doctor = User::factory()->create(['compensation_type' => 'commission', 'commission_consultation' => 70.00]);
        $doctor->assignRole('Doctor');
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);

        $this->createPaidInvoice([
            'patient_id' => $patient->id,
            'department' => 'consultation',
            'prescribing_doctor_id' => $doctor->id,
            'total_amount' => 1000,
            'created_at' => $today,
        ]);

        Expense::create([
            'department' => 'consultation',
            'description' => 'Test expense',
            'cost' => 100,
            'created_by' => $creator->id,
            'created_at' => $today,
        ]);

        $response = $this->actingAs($owner)->get(route('owner.financial-report', [
            'from' => $today->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('revenue', 1000);
        $response->assertViewHas('expenses', 100);
        $response->assertViewHas('net_profit', 200);
    }

    public function test_financial_report_handles_date_filtering(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $today = Carbon::today()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $creator = User::factory()->create();

        $yesterdayExpense = new Expense([
            'department' => 'lab',
            'description' => 'Yesterday expense',
            'cost' => 500,
            'created_by' => $creator->id,
        ]);
        $yesterdayExpense->created_at = $yesterday;
        $yesterdayExpense->save();

        $todayExpense = new Expense([
            'department' => 'lab',
            'description' => 'Today expense',
            'cost' => 300,
            'created_by' => $creator->id,
        ]);
        $todayExpense->created_at = $today;
        $todayExpense->save();

        $response = $this->actingAs($owner)->get(route('owner.financial-report', [
            'from' => $today->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('expenses', 300);
    }
}
