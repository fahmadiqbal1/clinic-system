<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiAnalysisResource;
use App\Models\AiAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AiAnalysisController extends Controller
{
    /**
     * Display a listing of AI analyses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $analyses = AiAnalysis::query()
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->context_type, function ($query, $contextType) {
                $query->where('context_type', $contextType);
            })
            ->when($request->patient_id, function ($query, $patientId) {
                $query->where('patient_id', $patientId);
            })
            // Scope based on user role
            ->when($user->hasRole('Doctor'), function ($query) use ($user) {
                $query->whereHas('patient', fn ($q) => $q->where('doctor_id', $user->id));
            })
            ->when($user->hasRole('Laboratory'), function ($query) {
                $query->where('context_type', 'lab');
            })
            ->when($user->hasRole('Radiology'), function ($query) {
                $query->where('context_type', 'radiology');
            })
            ->with(['patient', 'requester'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return AiAnalysisResource::collection($analyses);
    }

    /**
     * Display the specified AI analysis.
     */
    public function show(AiAnalysis $analysis): AiAnalysisResource
    {
        $this->authorize('view', $analysis);

        $analysis->load(['patient', 'invoice', 'requester']);

        return new AiAnalysisResource($analysis);
    }
}
