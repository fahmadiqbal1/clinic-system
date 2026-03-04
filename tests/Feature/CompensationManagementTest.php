<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class CompensationManagementTest extends TestCase
{
    private User $owner;
    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');

        $this->doctor = User::factory()->create(['compensation_type' => 'commission']);
        $this->doctor->assignRole('Doctor');
    }

    public function test_owner_can_view_edit_user_with_commission_fields(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.users.edit', $this->doctor));
        $response->assertStatus(200);
        $response->assertSee('Commission Rates');
        $response->assertSee('commission_consultation');
        $response->assertSee('commission_pharmacy');
        $response->assertSee('commission_lab');
        $response->assertSee('commission_radiology');
    }

    public function test_owner_can_create_user_with_commission_rates(): void
    {
        $response = $this->actingAs($this->owner)->post(route('owner.users.store'), [
            'name' => 'New Doctor',
            'email' => 'newdoc@clinic.com',
            'password' => 'password123',
            'role_id' => \Spatie\Permission\Models\Role::findByName('Doctor')->id,
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
            'commission_pharmacy' => 15.00,
            'commission_lab' => 10.00,
            'commission_radiology' => 5.00,
        ]);

        $response->assertRedirect(route('owner.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'newdoc@clinic.com',
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
            'commission_pharmacy' => 15.00,
            'commission_lab' => 10.00,
            'commission_radiology' => 5.00,
        ]);
    }

    public function test_owner_can_update_user_commission_rates(): void
    {
        $response = $this->actingAs($this->owner)->patch(route('owner.users.update', $this->doctor), [
            'name' => $this->doctor->name,
            'email' => $this->doctor->email,
            'role_id' => \Spatie\Permission\Models\Role::findByName('Doctor')->id,
            'compensation_type' => 'commission',
            'commission_consultation' => 80.00,
            'commission_pharmacy' => 20.00,
            'commission_lab' => 0,
            'commission_radiology' => 0,
        ]);

        $response->assertRedirect(route('owner.users.index'));

        $this->doctor->refresh();
        $this->assertEquals(80.00, (float) $this->doctor->commission_consultation);
        $this->assertEquals(20.00, (float) $this->doctor->commission_pharmacy);
        $this->assertEquals(0, (float) $this->doctor->commission_lab);
        $this->assertEquals(0, (float) $this->doctor->commission_radiology);
    }

    public function test_salaried_user_commission_rates_forced_to_zero(): void
    {
        $response = $this->actingAs($this->owner)->patch(route('owner.users.update', $this->doctor), [
            'name' => $this->doctor->name,
            'email' => $this->doctor->email,
            'role_id' => \Spatie\Permission\Models\Role::findByName('Doctor')->id,
            'compensation_type' => 'salaried',
            'base_salary' => 5000,
            'commission_consultation' => 70.00,
            'commission_pharmacy' => 15.00,
            'commission_lab' => 10.00,
            'commission_radiology' => 5.00,
        ]);

        $response->assertRedirect(route('owner.users.index'));

        $this->doctor->refresh();
        $this->assertEquals('salaried', $this->doctor->compensation_type);
        $this->assertEquals(0, (float) $this->doctor->commission_consultation);
        $this->assertEquals(0, (float) $this->doctor->commission_pharmacy);
        $this->assertEquals(0, (float) $this->doctor->commission_lab);
        $this->assertEquals(0, (float) $this->doctor->commission_radiology);
    }

    public function test_hybrid_user_gets_salary_and_commission_rates(): void
    {
        $response = $this->actingAs($this->owner)->patch(route('owner.users.update', $this->doctor), [
            'name' => $this->doctor->name,
            'email' => $this->doctor->email,
            'role_id' => \Spatie\Permission\Models\Role::findByName('Doctor')->id,
            'compensation_type' => 'hybrid',
            'base_salary' => 3000,
            'commission_consultation' => 50.00,
            'commission_pharmacy' => 10.00,
            'commission_lab' => 0,
            'commission_radiology' => 0,
        ]);

        $response->assertRedirect(route('owner.users.index'));

        $this->doctor->refresh();
        $this->assertEquals('hybrid', $this->doctor->compensation_type);
        $this->assertEquals(3000.00, (float) $this->doctor->base_salary);
        $this->assertEquals(50.00, (float) $this->doctor->commission_consultation);
        $this->assertEquals(10.00, (float) $this->doctor->commission_pharmacy);
    }

    public function test_commission_percentage_cannot_exceed_100(): void
    {
        $response = $this->actingAs($this->owner)->patch(route('owner.users.update', $this->doctor), [
            'name' => $this->doctor->name,
            'email' => $this->doctor->email,
            'role_id' => \Spatie\Permission\Models\Role::findByName('Doctor')->id,
            'compensation_type' => 'commission',
            'commission_consultation' => 150.00,
        ]);

        $response->assertSessionHasErrors('commission_consultation');
    }

    public function test_non_owner_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->doctor)->get(route('owner.users.index'));
        $response->assertStatus(403);
    }

    public function test_commission_rate_for_returns_zero_for_salaried(): void
    {
        $user = User::factory()->create([
            'compensation_type' => 'salaried',
            'commission_consultation' => 70.00,
        ]);

        $this->assertEquals(0, $user->commissionRateFor('consultation'));
    }

    public function test_commission_rate_for_returns_rate_for_commission_type(): void
    {
        $user = User::factory()->create([
            'compensation_type' => 'commission',
            'commission_consultation' => 70.00,
            'commission_pharmacy' => 15.00,
            'commission_lab' => 10.00,
            'commission_radiology' => 5.00,
        ]);

        $this->assertEquals(70.00, $user->commissionRateFor('consultation'));
        $this->assertEquals(15.00, $user->commissionRateFor('pharmacy'));
        $this->assertEquals(10.00, $user->commissionRateFor('lab'));
        $this->assertEquals(5.00, $user->commissionRateFor('radiology'));
    }

    public function test_commission_rate_for_returns_rate_for_hybrid_type(): void
    {
        $user = User::factory()->create([
            'compensation_type' => 'hybrid',
            'base_salary' => 5000,
            'commission_consultation' => 50.00,
        ]);

        $this->assertEquals(50.00, $user->commissionRateFor('consultation'));
    }
}
