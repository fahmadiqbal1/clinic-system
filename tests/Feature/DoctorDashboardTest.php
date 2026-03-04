<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class DoctorDashboardTest extends TestCase
{

    public function test_guest_cannot_access_doctor_dashboard(): void
    {
        $response = $this->get('/doctor/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_owner_cannot_access_doctor_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->get('/doctor/dashboard');
        $response->assertStatus(403);
    }

    public function test_doctor_can_access_dashboard(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $response = $this->actingAs($doctor)->get('/doctor/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('doctor.dashboard');
    }

    public function test_doctor_dashboard_shows_patient_count(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        Patient::factory(3)->create(['doctor_id' => $doctor->id]);

        $response = $this->actingAs($doctor)->get('/doctor/dashboard');
        $response->assertStatus(200);
        $response->assertViewHas('patientCount', 3);
    }
}
