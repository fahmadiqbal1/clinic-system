<?php

namespace Tests\Feature\Kpi;

use App\Models\DoctorPayout;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PlatformSetting;
use App\Models\RevenueLedger;
use App\Models\User;
use App\Services\DoctorPayoutService;
use App\Services\KpiService;
use Carbon\Carbon;
use Tests\TestCase;

class KpiServiceTest extends TestCase
{
    private KpiService $kpi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kpi = app(KpiService::class);

        // Seed GP tier platform_settings
        PlatformSetting::updateOrCreate(
            ['platform_name' => 'gp.tier2.patients_threshold', 'provider' => 'hr_config'],
            ['meta' => ['value' => 30]]
        );
        PlatformSetting::updateOrCreate(
            ['platform_name' => 'gp.tier2.bonus', 'provider' => 'hr_config'],
            ['meta' => ['value' => 5000]]
        );
        PlatformSetting::updateOrCreate(
            ['platform_name' => 'gp.tier3.patients_threshold', 'provider' => 'hr_config'],
            ['meta' => ['value' => 60]]
        );
        PlatformSetting::updateOrCreate(
            ['platform_name' => 'gp.tier3.bonus', 'provider' => 'hr_config'],
            ['meta' => ['value' => 10000]]
        );
    }

    public function test_nps_formula_returns_correct_percentage(): void
    {
        $doctor = User::factory()->create([
            'compensation_type' => 'commission',
            'base_salary'       => 0,
        ]);
        $doctor->assignRole('Doctor');

        // Revenue: 100,000 via paid invoice
        Invoice::factory()->create([
            'prescribing_doctor_id' => $doctor->id,
            'total_amount'          => 100000,
            'status'                => 'paid',
            'paid_at'               => now(),
        ]);

        // Compensation via payout: 20,000
        DoctorPayout::create([
            'doctor_id'    => $doctor->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end'   => now()->toDateString(),
            'total_amount' => 20000,
            'paid_amount'  => 20000,
            'status'       => 'confirmed',
            'payout_type'  => DoctorPayout::TYPE_COMMISSION,
            'created_by'   => $doctor->id,
        ]);

        $nps = $this->kpi->staffNps($doctor, now()->startOfMonth(), now());

        // NPS = ((100000 - 20000) / 100000) * 100 = 80%
        $this->assertEqualsWithDelta(80.0, $nps, 0.5);
    }

    public function test_revenue_attributed_sums_paid_invoices_for_user(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        Invoice::factory()->create([
            'prescribing_doctor_id' => $doctor->id,
            'total_amount'          => 40000,
            'status'                => 'paid',
            'paid_at'               => now(),
        ]);
        Invoice::factory()->create([
            'prescribing_doctor_id' => $doctor->id,
            'total_amount'          => 10000,
            'status'                => 'paid',
            'paid_at'               => now(),
        ]);
        // Unpaid invoice — must not be counted
        Invoice::factory()->create([
            'prescribing_doctor_id' => $doctor->id,
            'total_amount'          => 99999,
            'status'                => 'pending',
        ]);

        $revenue = $this->kpi->revenueAttributed($doctor, now()->startOfMonth(), now());

        $this->assertEqualsWithDelta(50000.0, $revenue, 0.01);
    }

    public function test_gp_tier_resolves_correctly_from_platform_settings(): void
    {
        $gp = User::factory()->create(['employee_type' => 'gp']);
        $gp->assignRole('Doctor');

        // Below tier 2 threshold (30) → tier 1
        $this->assertSame(1, $gp->gpTier(20));

        // At tier 2 threshold → tier 2
        $this->assertSame(2, $gp->gpTier(30));

        // At tier 3 threshold → tier 3
        $this->assertSame(3, $gp->gpTier(60));
    }

    public function test_gp_tier_bonus_is_added_to_monthly_payout(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $gp = User::factory()->create([
            'employee_type'     => 'gp',
            'compensation_type' => 'salaried',
            'base_salary'       => 30000,
        ]);
        $gp->assignRole('Doctor');

        // Create 35 patients for this GP in current month (reaches tier 2)
        Patient::factory()->count(35)->create([
            'doctor_id'  => $gp->id,
            'created_at' => now(),
        ]);

        $service = app(DoctorPayoutService::class);
        $payout  = $service->generateMonthlyPayout(
            staff:       $gp,
            periodStart: now()->startOfMonth(),
            periodEnd:   now()->endOfMonth(),
            paidAmount:  30000,  // just base salary
            createdBy:   $owner,
        );

        // Base 30,000 + tier 2 bonus 5,000 = 35,000
        $this->assertEqualsWithDelta(35000.0, $payout->salary_amount, 0.01);

        $notes = json_decode($payout->notes, true);
        $this->assertSame(2, $notes['gp_tier']);
        $this->assertEquals(5000, $notes['gp_tier_bonus']);
    }
}
