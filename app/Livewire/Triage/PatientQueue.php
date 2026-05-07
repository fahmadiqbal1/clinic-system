<?php

namespace App\Livewire\Triage;

use App\Models\Patient;
use Livewire\Attributes\On;
use Livewire\Component;

class PatientQueue extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $patients = [];

    public function mount(): void
    {
        $this->loadPatients();
    }

    /**
     * Livewire Echo listener — fired when Reverb broadcasts PatientStatusChanged
     * on the public 'triage' channel.
     */
    #[On('echo:triage,PatientStatusChanged')]
    public function onPatientStatusChanged(array $event): void
    {
        $this->loadPatients();
    }

    public function refreshQueue(): void
    {
        $this->loadPatients();
    }

    public function render()
    {
        return view('livewire.triage.patient-queue');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function loadPatients(): void
    {
        // Show patients who are currently in the triage workflow:
        //   'registered' = waiting to be triaged
        //   'triage'     = currently being triaged
        // (with_doctor, completed etc. are handled by doctor/receptionist views)
        $this->patients = Patient::whereIn('status', ['registered', 'triage'])
            ->orderByRaw("FIELD(status, 'triage', 'registered')")
            ->orderBy('registered_at')
            ->get(['id', 'first_name', 'last_name', 'status', 'registered_at', 'triage_started_at'])
            ->map(fn (Patient $p) => [
                'id'             => $p->id,
                'name'           => trim("{$p->first_name} {$p->last_name}"),
                'status'         => $p->status,
                'registered_at'  => $p->registered_at?->toDateTimeString(),
                'triage_started_at' => $p->triage_started_at?->toDateTimeString(),
            ])
            ->all();
    }
}
