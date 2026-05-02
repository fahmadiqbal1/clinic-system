<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\SoapKeyword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoapKeywordController extends Controller
{
    private const PROMOTE_THRESHOLD = 3;

    /**
     * List all chips visible to the authenticated doctor, grouped by section.
     * Called once on page load; result is embedded in window.soapKeywords via Blade,
     * so this endpoint is only needed for dynamic reloads (not typical in v1).
     */
    public function index(): JsonResponse
    {
        $keywords = SoapKeyword::forDoctor(Auth::id())
            ->orderBy('usage_count', 'desc')
            ->get()
            ->groupBy('section');

        return response()->json($keywords);
    }

    /**
     * Create a new chip for this doctor, or increment usage_count if one with the
     * same canonical key already exists for this doctor (or globally).
     *
     * Application-layer deduplication — no DB unique constraint because MariaDB 10.4
     * treats NULL != NULL in unique indexes, making global-chip uniqueness impossible
     * to enforce at the DB level without a sentinel value.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section'      => ['required', 'in:S,O,A,P'],
            'display_text' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $canonical = SoapKeyword::canonicalize($validated['display_text']);

        // 1. Look for this doctor's own chip with the same canonical key
        $existing = SoapKeyword::where('section', $validated['section'])
            ->where('canonical_key', $canonical)
            ->where('doctor_id', Auth::id())
            ->first();

        // 2. If not found, check for a global chip
        if (!$existing) {
            $existing = SoapKeyword::where('section', $validated['section'])
                ->where('canonical_key', $canonical)
                ->whereNull('doctor_id')
                ->first();
        }

        if ($existing) {
            $existing->increment('usage_count');
            // Promote doctor-specific chip to global once threshold is reached
            if ($existing->usage_count >= self::PROMOTE_THRESHOLD && $existing->doctor_id !== null) {
                $existing->update(['doctor_id' => null]);
            }
            return response()->json($existing->fresh());
        }

        $keyword = SoapKeyword::create([
            'section'       => $validated['section'],
            'display_text'  => trim($validated['display_text']),
            'canonical_key' => $canonical,
            'doctor_id'     => Auth::id(),
            'usage_count'   => 1,
        ]);

        return response()->json($keyword, 201);
    }

    /**
     * Increment usage for a chip the doctor has clicked.
     * Authorises that the chip is either global or belongs to this doctor.
     */
    public function use(SoapKeyword $soapKeyword): JsonResponse
    {
        // Global chips (doctor_id = null) may be used by any doctor.
        // Private chips (doctor_id set) only by their owner.
        if ($soapKeyword->doctor_id !== null && $soapKeyword->doctor_id !== Auth::id()) {
            abort(403, 'You do not have access to this keyword.');
        }

        $soapKeyword->increment('usage_count');

        if ($soapKeyword->usage_count >= self::PROMOTE_THRESHOLD && $soapKeyword->doctor_id !== null) {
            $soapKeyword->update(['doctor_id' => null]);
        }

        return response()->json([
            'id'          => $soapKeyword->id,
            'usage_count' => $soapKeyword->fresh()->usage_count,
            'is_global'   => $soapKeyword->fresh()->doctor_id === null,
        ]);
    }
}
