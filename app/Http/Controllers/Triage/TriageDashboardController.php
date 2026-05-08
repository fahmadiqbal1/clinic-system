<?php

namespace App\Http\Controllers\Triage;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\KpiService;
use Illuminate\View\View;

class TriageDashboardController extends Controller
{
    public function __construct(private readonly KpiService $kpi) {}

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

        $user        = auth()->user();
        $monthStart  = now()->startOfMonth();
        $shiftSummary = $this->kpi->shiftSummary($user, $monthStart, now());

        return view('triage.dashboard', [
            'registeredCount'    => $registeredCount,
            'triageCount'        => $triageCount,
            'readyForDoctorCount' => $readyForDoctorCount,
            'completedTodayCount' => $completedTodayCount,
            'waitingQueue'       => $waitingQueue,
            'inTriagePatients'   => $inTriagePatients,
            'kpi' => [
                'vitals_today'        => \App\Models\TriageVital::whereDate('created_at', today())->where('recorded_by', $user->id)->count(),
                'vitals_month'        => \App\Models\TriageVital::whereMonth('created_at', now()->month)->where('recorded_by', $user->id)->count(),
                'avg_wait_mins'       => $this->kpi->avgWaitMinutes($user, $monthStart, now()),
                'priority_high_today' => \App\Models\TriageVital::whereDate('created_at', today())->whereIn('priority', ['high', 'urgent', 'critical', 'emergency'])->count(),
                'shifts_month'        => $shiftSummary['shifts'],
                'hours_month'         => $shiftSummary['hours'],
            ],
        ]);
    }
}
