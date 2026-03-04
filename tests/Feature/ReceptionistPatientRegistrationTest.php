<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\ServiceCatalog;
use App\Models\User;
use Tests\TestCase;

class ReceptionistPatientRegistrationTest extends TestCase
{

    public function test_guest_cannot_access_patient_registration(): void
    {
        $response = $this->get('/receptionist/patients/create');
        $response->assertRedirect('/login');
    }

    public function test_doctor_cannot_access_patient_registration(): void
    {
        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $response = $this->actingAs($doctor)->get('/receptionist/patients/create');
        $response->assertStatus(403);
    }

    public function test_receptionist_can_see_registration_form(): void
    {
        /** @var User $receptionist */
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $response = $this->actingAs($receptionist)->get('/receptionist/patients/create');
        $response->assertStatus(200);
        $response->assertViewIs('receptionist.patients.create');
    }

    public function test_receptionist_can_register_patient_with_doctor(): void
    {
        /** @var User $receptionist */
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $service = ServiceCatalog::create([
            'department' => 'consultation',
            'name' => 'General Consultation',
            'code' => 'CONS-001',
            'price' => 100.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($receptionist)->post('/receptionist/patients', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'doctor_id' => $doctor->id,
            'service_catalog_id' => $service->id,
            'consultation_fee' => 100.00,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect(route('receptionist.patients.index'));
        $this->assertDatabaseHas('patients', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'doctor_id' => $doctor->id,
            'status' => 'registered',
        ]);
    }

    public function test_patient_registered_at_is_set(): void
    {
        /** @var User $receptionist */
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $service = ServiceCatalog::create([
            'department' => 'consultation',
            'name' => 'General Consultation',
            'code' => 'CONS-002',
            'price' => 100.00,
            'is_active' => true,
        ]);

        $this->actingAs($receptionist)->post('/receptionist/patients', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '0987654321',
            'gender' => 'Female',
            'date_of_birth' => '1995-05-15',
            'doctor_id' => $doctor->id,
            'service_catalog_id' => $service->id,
            'consultation_fee' => 100.00,
            'payment_method' => 'cash',
        ]);

        $patient = Patient::where('first_name', 'Jane')->first();
        $this->assertNotNull($patient->registered_at);
        $this->assertEquals($patient->status, 'registered');
    }

    public function test_receptionist_cannot_assign_non_doctor(): void
    {
        /** @var User $receptionist */
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($receptionist)->post('/receptionist/patients', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'gender' => 'Male',
            'doctor_id' => $owner->id,
        ]);

        $response->assertRedirect();
    }

    public function test_receptionist_can_view_registered_patients(): void
    {
        /** @var User $receptionist */
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        /** @var User $doctor */
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status' => 'registered',
        ]);

        $response = $this->actingAs($receptionist)->get('/receptionist/patients');
        $response->assertStatus(200);
        $response->assertViewIs('receptionist.patients.index');
        $response->assertSee($patient->first_name);
    }
}
