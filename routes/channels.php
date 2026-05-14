<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private per-owner channel for real-time AI critical alerts.
// Only the authenticated Owner whose ID matches the channel suffix may subscribe.
Broadcast::channel('owner-alerts.{ownerId}', function ($user, $ownerId) {
    return (int) $user->id === (int) $ownerId && $user->hasRole('Owner');
});

// Triage status updates (existing — used by PatientStatusChanged event).
Broadcast::channel('triage', function ($user) {
    return $user->hasRole('Triage') || $user->hasRole('Doctor') || $user->hasRole('Owner');
});
