<?php

namespace Tests\Feature\Events;

use App\Events\PatientStatusChanged;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PatientStatusChangedTest extends TestCase
{
    public function test_observer_dispatches_patient_status_changed_on_status_update(): void
    {
        Event::fake([PatientStatusChanged::class]);

        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $patient = Patient::factory()->create(['status' => 'registered']);

        $patient->update(['status' => 'triage']);

        Event::assertDispatched(PatientStatusChanged::class, function ($event) use ($patient) {
            return $event->patientId === $patient->id
                && $event->status === 'triage';
        });
    }
}
