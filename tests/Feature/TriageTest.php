<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class TriageTest extends TestCase
{

    public function test_guest_cannot_access_triage_dashboard(): void
    {
        $response = $this->get('/triage/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_non_triage_user_cannot_access_triage(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $response = $this->actingAs($doctor)->get('/triage/dashboard');
        $response->assertStatus(403);
    }

    public function test_triage_can_access_dashboard(): void
    {
        /** @var User $triage */
        $triage = User::factory()->create();
        $triage->assignRole('Triage');

        $response = $this->actingAs($triage)->get('/triage/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('triage.dashboard');
    }

    public function test_triage_sees_only_registered_patients(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        /** @var User $triage */
        $triage = User::factory()->create();
        $triage->assignRole('Triage');

        // Create registered patient
        $registeredPatient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'registered',
        ]);

        // Create patient in triage
        $triagePatient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'triage',
        ]);

        $response = $this->actingAs($triage)->get('/triage/patients');
        $response->assertStatus(200);
        $response->assertSee($registeredPatient->first_name);
        $response->assertSee($triagePatient->first_name);
    }

    public function test_triage_can_record_vitals(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        /** @var User $triage */
        $triage = User::factory()->create();
        $triage->assignRole('Triage');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'registered',
        ]);

        $response = $this->actingAs($triage)->post("/triage/patients/{$patient->id}/vitals", [
            'blood_pressure' => '120/80',
            'temperature' => 37.5,
            'pulse_rate' => 72,
            'priority' => 'normal',
            'notes' => 'Patient appears healthy',
        ]);

        $response->assertRedirect(route('triage.patients.index'));
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'status' => 'triage',
        ]);
        $this->assertNotNull($patient->fresh()->triage_started_at);
    }

    public function test_triage_can_send_patient_to_doctor(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        /** @var User $triage */
        $triage = User::factory()->create();
        $triage->assignRole('Triage');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'triage',
            'triage_started_at' => now(),
        ]);

        $response = $this->actingAs($triage)->post("/triage/patients/{$patient->id}/send-to-doctor");

        $response->assertRedirect(route('triage.patients.index'));
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'status' => 'with_doctor',
        ]);
        $this->assertNotNull($patient->fresh()->doctor_started_at);
    }

    public function test_triage_cannot_record_vitals_for_non_registered_patient(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        /** @var User $triage */
        $triage = User::factory()->create();
        $triage->assignRole('Triage');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->actingAs($triage)->post("/triage/patients/{$patient->id}/vitals", [
            'blood_pressure' => '120/80',
            'priority' => 'normal',
        ]);

        $response->assertStatus(403);
    }

    public function test_triage_cannot_send_non_triage_patient_to_doctor(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        /** @var User $triage */
        $triage = User::factory()->create();
        $triage->assignRole('Triage');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'registered',
        ]);

        $response = $this->actingAs($triage)->post("/triage/patients/{$patient->id}/send-to-doctor");

        $response->assertStatus(403);
    }
}
