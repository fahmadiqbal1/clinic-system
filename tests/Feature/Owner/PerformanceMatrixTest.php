<?php

namespace Tests\Feature\Owner;

use App\Models\Invoice;
use App\Models\User;
use Tests\TestCase;

class PerformanceMatrixTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
    }

    public function test_performance_matrix_loads_all_non_owner_active_staff(): void
    {
        $doctor = User::factory()->create(['is_active' => true]);
        $doctor->assignRole('Doctor');

        $pharmacist = User::factory()->create(['is_active' => true]);
        $pharmacist->assignRole('Pharmacy');

        // Inactive user should not appear
        $inactive = User::factory()->create(['is_active' => false]);
        $inactive->assignRole('Doctor');

        $r = $this->actingAs($this->owner)->get('/owner/performance-matrix');
        $r->assertOk();

        $r->assertSee($doctor->name);
        $r->assertSee($pharmacist->name);
        $r->assertDontSee($inactive->name);
    }

    public function test_performance_matrix_ranks_by_nps_descending(): void
    {
        // High NPS: 100k revenue, 0 compensation → NPS ≈ 100%
        $highNps = User::factory()->create([
            'is_active'         => true,
            'compensation_type' => 'commission',
            'base_salary'       => 0,
        ]);
        $highNps->assignRole('Doctor');

        // Low NPS: 5k revenue, 60k salary (prorated) → NPS negative
        $lowNps = User::factory()->create([
            'is_active'         => true,
            'compensation_type' => 'salaried',
            'base_salary'       => 60000,
        ]);
        $lowNps->assignRole('Doctor');

        Invoice::factory()->create([
            'prescribing_doctor_id' => $highNps->id,
            'total_amount'          => 100000,
            'status'                => 'paid',
            'paid_at'               => now(),
        ]);

        Invoice::factory()->create([
            'prescribing_doctor_id' => $lowNps->id,
            'total_amount'          => 5000,
            'status'                => 'paid',
            'paid_at'               => now(),
        ]);

        $r = $this->actingAs($this->owner)->get('/owner/performance-matrix');
        $r->assertOk();

        // Higher NPS doctor should appear before lower NPS doctor (smaller string offset = earlier row)
        $body = $r->getContent();
        $this->assertLessThan(
            strpos($body, $lowNps->name),
            strpos($body, $highNps->name)
        );
    }
}
