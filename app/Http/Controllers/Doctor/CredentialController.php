<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\DoctorCredential;
use App\Models\User;
use App\Notifications\GenericOwnerAlert;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CredentialController extends Controller
{
    /**
     * Show credential upload form.
     */
    public function upload(): View
    {
        return view('doctor.credentials.upload');
    }

    /**
     * Store uploaded credential files.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'medical_license' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'degree'          => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $user = auth()->user();
        $userId = $user->id;
        $now = now();

        $uploads = [
            'medical_license' => $request->file('medical_license'),
            'degree'          => $request->file('degree'),
        ];

        foreach ($uploads as $type => $file) {
            $directory = "credentials/{$userId}";
            $path = $file->store($directory, 'local');

            $credential = DoctorCredential::create([
                'user_id'           => $userId,
                'type'              => $type,
                'file_path'         => $path,
                'original_filename' => $file->getClientOriginalName(),
                'uploaded_at'       => $now,
            ]);

            AuditableService::logCreate($credential, 'doctor_credential', [
                'type'              => $type,
                'original_filename' => $file->getClientOriginalName(),
            ]);
        }

        $user->update(['credentials_submitted_at' => $now]);

        $reviewUrl = route('owner.credentials.doctor', $user);
        User::role('Owner')->each(fn ($owner) => $owner->notify(new GenericOwnerAlert(
            message: "Dr. {$user->name} has submitted credentials for review.",
            icon: 'bi-person-badge',
            color: 'warning',
            url: $reviewUrl,
            title: 'Credential Submission',
        )));

        AuditableService::logTransition(
            $user,
            'user',
            'credentials_submitted_at',
            null,
            $now->toDateTimeString()
        );

        return redirect()->route('doctor.dashboard')
            ->with('success', 'Your credentials have been submitted and are pending verification.');
    }
}
