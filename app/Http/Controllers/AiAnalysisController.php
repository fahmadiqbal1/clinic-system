<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\Invoice;
use App\Models\Patient;
use App\Providers\AppServiceProvider;
use App\Services\MedGemmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AiAnalysisController extends Controller
{
    /**
     * Request AI analysis for a patient consultation.
     */
    public function analyseConsultation(Patient $patient, MedGemmaService $service): RedirectResponse
    {
        // Use policy instead of inline authorization
        $this->authorize('analyseConsultation', [AiAnalysis::class, $patient]);

        $service->analyseConsultation($patient, Auth::id());

        return redirect()->back()->with('success', 'MedGemma analysis queued. Results will appear shortly.');
    }

    /**
     * Request AI analysis for a lab invoice.
     */
    public function analyseLab(Invoice $invoice, MedGemmaService $service): RedirectResponse
    {
        $this->authorize('analyseLab', [AiAnalysis::class, $invoice]);

        $service->analyseLab($invoice, Auth::id());

        return redirect()->back()->with('success', 'MedGemma analysis queued. Results will appear shortly.');
    }

    /**
     * Request AI analysis for a radiology invoice.
     */
    public function analyseRadiology(Invoice $invoice, MedGemmaService $service): RedirectResponse
    {
        $this->authorize('analyseRadiology', [AiAnalysis::class, $invoice]);

        $service->analyseRadiology($invoice, Auth::id());

        return redirect()->back()->with('success', 'MedGemma analysis queued. Results will appear shortly.');
    }

    /**
     * Return the current status and response of a single analysis (for JS polling).
     */
    public function statusCheck(AiAnalysis $analysis): JsonResponse
    {
        $this->authorize('view', $analysis);

        return response()->json([
            'status'     => $analysis->status,
            'response'   => $analysis->ai_response,
            'updated_at' => $analysis->updated_at->diffForHumans(),
        ]);
    }

    /**
     * Queue a free-form quick chat analysis for a patient (Doctor only).
     */
    public function quickChat(Patient $patient, Request $request, MedGemmaService $service): JsonResponse
    {
        $this->authorize('analyseConsultation', [AiAnalysis::class, $patient]);

        $validated = $request->validate([
            'question' => 'required|string|min:5|max:1000',
        ]);

        $analysis = $service->analyseConsultation($patient, Auth::id(), $validated['question']);

        return response()->json([
            'analysis_id' => $analysis->id,
            'status'      => 'pending',
            'message'     => 'Question queued. Answer will appear shortly.',
        ]);
    }

    /**
     * Return whether the queue worker process is running (for UI status indicator).
     */
    public function workerStatus(): JsonResponse
    {
        return response()->json(AppServiceProvider::workerStatus());
    }

    /**
     * Manually start the queue worker if it is not running.
     * Acts as a fallback for when auto-start failed (e.g. shell_exec disabled).
     */
    public function workerStart(): JsonResponse
    {
        $status = AppServiceProvider::workerStatus();

        if ($status['running']) {
            return response()->json(['started' => false, 'message' => 'Worker is already running.']);
        }

        $provider = new AppServiceProvider(app());
        $provider->ensureQueueWorkerRunning(force: true);

        // Give the process 1 second to write its PID file, then re-check
        sleep(1);
        $newStatus = AppServiceProvider::workerStatus();

        return response()->json([
            'started' => $newStatus['running'],
            'message' => $newStatus['running']
                ? 'Queue worker started successfully.'
                : 'Could not start worker automatically. Please run start.bat manually.',
        ]);
    }

    /**
     * View all AI analyses for a patient.
     */
    public function patientAnalyses(Patient $patient): View
    {
        $this->authorize('view', $patient);

        $analyses = AiAnalysis::where('patient_id', $patient->id)
            ->with('requester')
            ->latest()
            ->get();

        return view('ai-analysis.patient-analyses', [
            'patient' => $patient,
            'analyses' => $analyses,
        ]);
    }
}
