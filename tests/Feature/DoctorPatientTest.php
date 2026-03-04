<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class DoctorPatientTest extends TestCase
{

    public function test_guest_cannot_access_patient_list(): void
    {
        $response = $this->get('/doctor/patients');
        $response->assertRedirect('/login');
    }

    public function test_non_doctor_cannot_access_patient_list(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->get('/doctor/patients');
        $response->assertStatus(403);
    }

    public function test_doctor_can_see_own_patients(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $patient1 = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'with_doctor',
        ]);
        $patient2 = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->actingAs($doctor)->get('/doctor/patients');
        $response->assertStatus(200);
        $response->assertViewIs('doctor.patients.index');
        $response->assertSee($patient1->first_name);
        $response->assertSee($patient2->first_name);
    }

    public function test_doctor_cannot_see_another_doctors_patients(): void
    {
        /** @var User $doctor1 */
        $doctor1 = User::factory()->create();
        $doctor1->assignRole('Doctor');

        /** @var User $doctor2 */
        $doctor2 = User::factory()->create();
        $doctor2->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor2->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->actingAs($doctor1)->get('/doctor/patients');
        $response->assertStatus(200);
        $response->assertDontSee($patient->first_name);
    }

    public function test_doctor_can_see_patient_details(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->actingAs($doctor)->get("/doctor/patients/{$patient->id}");
        $response->assertStatus(200);
        $response->assertViewIs('doctor.patients.show');
        $response->assertSee($patient->first_name);
        $response->assertSee($patient->last_name);
    }

    public function test_doctor_cannot_view_another_doctors_patient(): void
    {
        /** @var User $doctor1 */
        $doctor1 = User::factory()->create();
        $doctor1->assignRole('Doctor');

        /** @var User $doctor2 */
        $doctor2 = User::factory()->create();
        $doctor2->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor2->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->actingAs($doctor1)->get("/doctor/patients/{$patient->id}");
        $response->assertStatus(403);
    }

    public function test_guest_cannot_view_patient_details(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->get("/doctor/patients/{$patient->id}");
        $response->assertRedirect('/login');
    }

    public function test_doctor_can_see_all_patients_with_filter(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        // Patient with status 'with_doctor'
        $activePatient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'with_doctor',
        ]);

        // Patient with status 'triage'
        $triagePatient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'triage',
        ]);

        // Default (no filter): shows all patients
        $response = $this->actingAs($doctor)->get('/doctor/patients');
        $response->assertStatus(200);
        $response->assertSee($activePatient->first_name);
        $response->assertSee($triagePatient->first_name);

        // Filtered by with_doctor: shows only active
        $response = $this->actingAs($doctor)->get('/doctor/patients?status=with_doctor');
        $response->assertStatus(200);
        $response->assertSee($activePatient->first_name);
        $response->assertDontSee($triagePatient->first_name);
    }

    public function test_doctor_can_complete_patient(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->actingAs($doctor)->post("/doctor/patients/{$patient->id}/complete", [
            'consultation_notes' => 'Patient examined. Vitals normal. Prescribed medication for symptoms.',
        ]);

        $response->assertRedirect(route('doctor.patients.index'));
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull($patient->fresh()->completed_at);
    }

    public function test_doctor_cannot_complete_another_doctors_patient(): void
    {
        /** @var User $doctor1 */
        $doctor1 = User::factory()->create();
        $doctor1->assignRole('Doctor');

        /** @var User $doctor2 */
        $doctor2 = User::factory()->create();
        $doctor2->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor2->id,
            'status' => 'with_doctor',
        ]);

        $response = $this->actingAs($doctor1)->post("/doctor/patients/{$patient->id}/complete");

        $response->assertStatus(403);
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'status' => 'with_doctor',
        ]);
    }

    public function test_doctor_cannot_complete_patient_not_with_doctor(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'triage',
        ]);

        $response = $this->actingAs($doctor)->post("/doctor/patients/{$patient->id}/complete");

        $response->assertStatus(403);
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'status' => 'triage',
        ]);
    }
}
