<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $status = $request->query('status', 'all');

        $query = Appointment::forDoctor($user->id)->with('patient');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $appointments = $query->latest('scheduled_at')->paginate(20)->withQueryString();

        // Counts per status for tab badges
        $counts = Appointment::forDoctor($user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalCount = array_sum($counts);

        return view('doctor.appointments.index', compact('appointments', 'status', 'counts', 'totalCount'));
    }
}
