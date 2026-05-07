<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDocumentVerification
{
    /**
     * Handle an incoming request.
     *
     * For Doctor users who have not submitted credentials and whose account
     * is older than 48 hours, redirect to the credential upload page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->hasRole('Doctor')
            && $user->credentials_submitted_at === null
            && $user->created_at->diffInHours(now()) > 48
        ) {
            $routeName = $request->route()?->getName() ?? '';

            $bypassed = str_starts_with($routeName, 'doctor.credentials.')
                || $routeName === 'logout'
                || str_starts_with($routeName, 'profile.');

            if (!$bypassed) {
                return redirect()->route('doctor.credentials.upload')
                    ->with('warning', 'Please upload your medical credentials to continue using the system.');
            }
        }

        return $next($request);
    }
}
