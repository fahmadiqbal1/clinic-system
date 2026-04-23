<?php

namespace App\Services;

use App\Models\CaseToken;
use App\Models\Patient;

class CaseTokenService
{
    /**
     * Return a deterministic pseudonym for a patient.
     * Lazily populates the case_tokens lookup table on first call per patient.
     */
    public function tokenize(Patient $patient): string
    {
        $secret = config('clinic.case_token_secret');

        if (empty($secret)) {
            throw new \RuntimeException(
                'CLINIC_CASE_TOKEN_SECRET is not set. Set it in .env before using CaseTokenService.'
            );
        }

        $token = hash_hmac(
            'sha256',
            $patient->id . '|' . $patient->created_at->toIso8601String(),
            $secret
        );

        CaseToken::firstOrCreate(['token' => $token], ['patient_id' => $patient->id]);

        return $token;
    }

    /**
     * Resolve a case token back to a Patient. Owner-only operation.
     */
    public function resolve(string $token): ?Patient
    {
        $caseToken = CaseToken::where('token', $token)->first();

        return $caseToken ? Patient::find($caseToken->patient_id) : null;
    }

    /**
     * Compute 5-year age band (e.g. "30-34") from a Carbon date.
     * Returns "unknown" when date_of_birth is null.
     */
    public function ageBand(?object $dateOfBirth): string
    {
        if ($dateOfBirth === null) {
            return 'unknown';
        }

        $age   = (int) $dateOfBirth->age;
        $lower = (int) floor($age / 5) * 5;

        return "{$lower}-" . ($lower + 4);
    }
}
