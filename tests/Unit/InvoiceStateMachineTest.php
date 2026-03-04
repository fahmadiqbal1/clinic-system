<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceStateMachineTest extends TestCase
{
    private User $doctor;
    private User $receptionist;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = User::create([
            'name' => 'Dr. Test',
            'email' => 'doc@test.com',
            'password' => Hash::make('password'),
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
            'commission_lab' => 10.00,
        ]);
        $this->doctor->assignRole('Doctor');

        $this->receptionist = User::create([
            'name' => 'Receptionist Test',
            'email' => 'rec@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->receptionist->assignRole('Receptionist');

        $this->patient = Patient::create([
            'first_name'    => 'Test',
            'last_name'     => 'Patient',
            'phone'         => '0700000000',
            'gender'        => 'Male',
            'date_of_birth' => '1990-01-01',
            'doctor_id'     => $this->doctor->id,
            'status'        => 'registered',
            'registered_at' => now(),
        ]);
    }

    private function createPendingInvoice(): Invoice
    {
        return Invoice::create([
            'patient_id'           => $this->patient->id,
            'patient_type'         => 'clinic',
            'department'           => 'lab',
            'service_name'         => 'Test Service',
            'total_amount'         => 1000,
            'prescribing_doctor_id' => $this->doctor->id,
            'status'               => Invoice::STATUS_PENDING,
            'has_prescribed_items' => false,
        ]);
    }

    #[Test]
    public function illegal_transition_completed_to_pending_fails(): void
    {
        $invoice = $this->createPendingInvoice();
        $invoice->startWork();
        $invoice->markCompleted();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid status transition from completed to pending.');

        $invoice->update(['status' => Invoice::STATUS_PENDING]);
    }

    #[Test]
    public function paid_invoice_update_fails(): void
    {
        $invoice = $this->createPendingInvoice();
        $invoice->startWork();
        $invoice->markCompleted();
        $invoice->markPaid('cash', $this->receptionist->id);

        $invoice->refresh();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Paid invoices are immutable. Forbidden fields: service_name');

        $invoice->update(['service_name' => 'Tampered']);
    }

    #[Test]
    public function paid_invoice_delete_fails(): void
    {
        $invoice = $this->createPendingInvoice();
        $invoice->startWork();
        $invoice->markCompleted();
        $invoice->markPaid('cash', $this->receptionist->id);

        $invoice->refresh();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Paid invoices cannot be deleted.');

        $invoice->delete();
    }

    #[Test]
    public function non_receptionist_cannot_mark_paid(): void
    {
        $invoice = $this->createPendingInvoice();
        $invoice->startWork();
        $invoice->markCompleted();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Receptionist or Owner');

        $invoice->markPaid('cash', $this->doctor->id);
    }

    #[Test]
    public function valid_full_workflow_succeeds(): void
    {
        $invoice = $this->createPendingInvoice();

        $this->assertTrue($invoice->startWork());
        $this->assertEquals(Invoice::STATUS_IN_PROGRESS, $invoice->fresh()->status);

        $this->assertTrue($invoice->markCompleted());
        $this->assertEquals(Invoice::STATUS_COMPLETED, $invoice->fresh()->status);

        $this->assertTrue($invoice->markPaid('cash', $this->receptionist->id));

        $paid = $invoice->fresh();
        $this->assertEquals(Invoice::STATUS_PAID, $paid->status);
        $this->assertEquals('cash', $paid->payment_method);
        $this->assertEquals($this->receptionist->id, $paid->paid_by);
        $this->assertNotNull($paid->paid_at);
    }
}
