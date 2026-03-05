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
        $user = Auth::user();
        if ($patient->doctor_id !== $user->id) {
            abort(403, 'This patient is not assigned to you.');
        }

        $service->analyseConsultation($patient, $user->id);

        return redirect()->back()->with('success', 'MedGemma analysis requested. Results are shown below.');
    }

    /**
     * Request AI analysis for a lab invoice.
     */
    public function analyseLab(Invoice $invoice, MedGemmaService $service): RedirectResponse
    {
        if ($invoice->department !== 'lab') {
            abort(404);
        }

        $service->analyseLab($invoice, Auth::id());

        return redirect()->back()->with('success', 'MedGemma analysis completed.');
    }

    /**
     * Request AI analysis for a radiology invoice.
     */
    public function analyseRadiology(Invoice $invoice, MedGemmaService $service): RedirectResponse
    {
        if ($invoice->department !== 'radiology') {
            abort(404);
        }

        $service->analyseRadiology($invoice, Auth::id());

        return redirect()->back()->with('success', 'MedGemma analysis completed.');
    }

    /**
     * View all AI analyses for a patient.
     */
    public function patientAnalyses(Patient $patient): View
    {
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
