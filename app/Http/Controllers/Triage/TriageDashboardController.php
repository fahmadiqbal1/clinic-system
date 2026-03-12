<?php

namespace App\Http\Controllers\Triage;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\View\View;

class TriageDashboardController extends Controller
{
    /**
     * Show the triage dashboard.
     */
    public function index(): View
    {
        $registeredCount = Patient::where('status', 'registered')->count();
        $triageCount = Patient::where('status', 'triage')->count();
        $readyForDoctorCount = Patient::where('status', 'with_doctor')->count();
        $completedTodayCount = Patient::where('status', 'completed')
            ->whereDate('updated_at', now()->format('Y-m-d'))
            ->count();

        // Waiting queue: registered patients, oldest first
        $waitingQueue = Patient::where('status', 'registered')
            ->oldest()
            ->limit(10)
            ->get();

        // Currently in triage — sort by priority (emergency → urgent → routine) then by time
        $inTriagePatients = Patient::where('status', 'triage')
            ->with(['triageVitals' => fn ($q) => $q->latest()->limit(1)])
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->sortBy(function ($patient) {
                $priority = $patient->triageVitals->first()?->priority ?? 'routine';
                return match ($priority) {
                    'emergency' => 0,
                    'urgent'    => 1,
                    default     => 2,
                };
            })
            ->values();

        return view('triage.dashboard', [
            'registeredCount' => $registeredCount,
            'triageCount' => $triageCount,
            'readyForDoctorCount' => $readyForDoctorCount,
            'completedTodayCount' => $completedTodayCount,
            'waitingQueue' => $waitingQueue,
            'inTriagePatients' => $inTriagePatients,
        ]);
    }
}
