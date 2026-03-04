<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user && $user->hasRole('Owner')) {
            return redirect()->intended(route('owner.dashboard', absolute: false));
        } elseif ($user && $user->hasRole('Doctor')) {
            return redirect()->intended(route('doctor.dashboard', absolute: false));
        } elseif ($user && $user->hasRole('Receptionist')) {
            return redirect()->intended(route('receptionist.dashboard', absolute: false));
        } elseif ($user && $user->hasRole('Triage')) {
            return redirect()->intended(route('triage.dashboard', absolute: false));
        } elseif ($user && $user->hasRole('Laboratory')) {
            return redirect()->intended(route('laboratory.dashboard', absolute: false));
        } elseif ($user && $user->hasRole('Radiology')) {
            return redirect()->intended(route('radiology.dashboard', absolute: false));
        } elseif ($user && $user->hasRole('Pharmacy')) {
            return redirect()->intended(route('pharmacy.dashboard', absolute: false));
        }

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
