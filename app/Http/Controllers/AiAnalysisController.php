<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\MedGemmaService;
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
