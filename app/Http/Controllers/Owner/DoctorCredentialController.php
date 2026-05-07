<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\DoctorCredential;
use App\Models\User;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DoctorCredentialController extends Controller
{
    /**
     * List all doctors with credential status summary.
     */
    public function index(): View
    {
        $doctorRole = Role::findByName('Doctor');

        $doctors = User::role('Doctor')
            ->withCount([
                'credentials as total_credentials',
                'credentials as pending_count' => fn ($q) => $q->whereNull('verified_at'),
                'credentials as verified_count' => fn ($q) => $q->whereNotNull('verified_at'),
            ])
            ->orderBy('name')
            ->get();

        return view('owner.credentials.index', compact('doctors'));
    }

    /**
     * Show all credentials for a single doctor.
     */
    public function showDoctor(User $user): View
    {
        $credentials = $user->credentials()
            ->with('verifiedBy')
            ->orderBy('uploaded_at', 'desc')
            ->get();

        return view('owner.credentials.doctor', compact('user', 'credentials'));
    }

    /**
     * Verify a credential.
     */
    public function verify(DoctorCredential $credential): RedirectResponse
    {
        $credential->update([
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        // Also stamp the user if all their credentials are now verified
        $doctor = $credential->user;
        $allVerified = $doctor->credentials()->whereNull('verified_at')->doesntExist();
        if ($allVerified) {
            $doctor->update(['credentials_verified_at' => now()]);
        }

        AuditableService::logTransition(
            $credential,
            'doctor_credential',
            'verified_at',
            null,
            now()->toDateTimeString()
        );

        return redirect()->back()->with('success', 'Credential verified successfully.');
    }

    /**
     * Reject / add notes to a credential.
     */
    public function reject(DoctorCredential $credential, Request $request): RedirectResponse
    {
        $request->validate([
            'verification_notes' => ['required', 'string', 'max:1000'],
        ]);

        $credential->update([
            'verification_notes' => $request->verification_notes,
            'verified_at'        => null,
        ]);

        AuditableService::logTransition(
            $credential,
            'doctor_credential',
            'verification_notes',
            null,
            $request->verification_notes
        );

        return redirect()->back()->with('success', 'Credential marked as requiring resubmission.');
    }
}
