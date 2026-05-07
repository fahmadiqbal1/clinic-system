<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\SoapKeyword;
use App\Models\User;
use Tests\TestCase;

class SoapKeywordTest extends TestCase
{
    // ─── Auth / role guards ──────────────────────────────────────────────────

    public function test_guest_cannot_list_soap_keywords(): void
    {
        $response = $this->getJson('/doctor/soap-keywords');
        $response->assertStatus(401);
    }

    public function test_non_doctor_cannot_list_soap_keywords(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->getJson('/doctor/soap-keywords');
        $response->assertStatus(403);
    }

    // ─── Index ───────────────────────────────────────────────────────────────

    public function test_doctor_can_list_keywords_grouped_by_section(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        SoapKeyword::create([
            'section' => 'S', 'display_text' => 'Fever',
            'canonical_key' => 'fever', 'doctor_id' => null, 'usage_count' => 0,
        ]);
        SoapKeyword::create([
            'section' => 'A', 'display_text' => 'URTI',
            'canonical_key' => 'urti', 'doctor_id' => null, 'usage_count' => 0,
        ]);

        $response = $this->actingAs($doctor)->getJson('/doctor/soap-keywords');
        $response->assertStatus(200);
        $response->assertJsonStructure(['S', 'A']);
    }

    // ─── Store ───────────────────────────────────────────────────────────────

    public function test_doctor_can_store_new_keyword(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $response = $this->actingAs($doctor)->postJson('/doctor/soap-keywords', [
            'section'      => 'S',
            'display_text' => 'Chest tightness',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['display_text' => 'Chest tightness', 'section' => 'S']);
        $this->assertDatabaseHas('soap_keywords', [
            'section'       => 'S',
            'canonical_key' => SoapKeyword::canonicalize('Chest tightness'),
            'doctor_id'     => $doctor->id,
        ]);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        // Missing section
        $this->actingAs($doctor)->postJson('/doctor/soap-keywords', [
            'display_text' => 'Fever',
        ])->assertStatus(422);

        // Invalid section
        $this->actingAs($doctor)->postJson('/doctor/soap-keywords', [
            'section'      => 'X',
            'display_text' => 'Fever',
        ])->assertStatus(422);

        // Too short display_text
        $this->actingAs($doctor)->postJson('/doctor/soap-keywords', [
            'section'      => 'S',
            'display_text' => 'F',
        ])->assertStatus(422);
    }

    // ─── Deduplication & promotion ───────────────────────────────────────────

    public function test_duplicate_keyword_increments_usage_count(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        // First store → creates with usage_count = 1
        $this->actingAs($doctor)->postJson('/doctor/soap-keywords', [
            'section' => 'S', 'display_text' => 'Fever',
        ])->assertStatus(201);

        // Second store of same canonical → increments to 2, does not create new row
        $this->actingAs($doctor)->postJson('/doctor/soap-keywords', [
            'section' => 'S', 'display_text' => 'Fever 3 days', // same canonical → "fever"
        ])->assertStatus(200);

        $this->assertDatabaseCount('soap_keywords', 1);
        $this->assertDatabaseHas('soap_keywords', ['canonical_key' => 'fever', 'usage_count' => 2]);
    }

    public function test_keyword_promoted_to_global_at_threshold(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        // Seed the chip at usage_count = 2 for this doctor
        $kw = SoapKeyword::create([
            'section'       => 'S',
            'display_text'  => 'Nausea',
            'canonical_key' => SoapKeyword::canonicalize('Nausea'),
            'doctor_id'     => $doctor->id,
            'usage_count'   => 2,
        ]);

        // Third POST → should promote to global (doctor_id = null)
        $response = $this->actingAs($doctor)->postJson('/doctor/soap-keywords', [
            'section' => 'S', 'display_text' => 'Nausea',
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('soap_keywords', [
            'id'        => $kw->id,
            'doctor_id' => null,
            'usage_count' => 3,
        ]);
    }

    // ─── Use endpoint ────────────────────────────────────────────────────────

    public function test_doctor_cannot_use_another_doctors_private_chip(): void
    {
        $doctor1 = User::factory()->create();
        $doctor1->assignRole('Doctor');
        $doctor2 = User::factory()->create();
        $doctor2->assignRole('Doctor');

        $kw = SoapKeyword::create([
            'section'       => 'A',
            'display_text'  => 'Private diagnosis',
            'canonical_key' => 'private diagnosis',
            'doctor_id'     => $doctor2->id,
            'usage_count'   => 0,
        ]);

        $response = $this->actingAs($doctor1)->postJson("/doctor/soap-keywords/{$kw->id}/use");
        $response->assertStatus(403);
    }

    public function test_doctor_can_use_global_chip(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $kw = SoapKeyword::create([
            'section'       => 'S',
            'display_text'  => 'Fever',
            'canonical_key' => 'fever',
            'doctor_id'     => null,
            'usage_count'   => 5,
        ]);

        $response = $this->actingAs($doctor)->postJson("/doctor/soap-keywords/{$kw->id}/use");
        $response->assertStatus(200);
        $this->assertDatabaseHas('soap_keywords', ['id' => $kw->id, 'usage_count' => 6]);
    }

    // ─── Consultation view integration ───────────────────────────────────────

    public function test_consultation_view_loads_with_soap_builder(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('Doctor');

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'status'    => 'with_doctor',
        ]);

        $response = $this->actingAs($doctor)->get("/doctor/consultations/{$patient->id}");

        $response->assertStatus(200);
        $response->assertSee('soapBuilder', false);
        $response->assertSee('soapBuilderForm', false);
        $response->assertSee('consultationNotesTA', false);
    }
}
