<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\StaffShift;
use App\Models\User;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $from   = Carbon::parse($request->get('from', now()->startOfMonth()));
        $to     = Carbon::parse($request->get('to', now()->endOfDay()));
        $userId = $request->get('user_id');

        $query = StaffShift::with('user')
            ->whereBetween('clocked_in_at', [$from, $to])
            ->orderBy('clocked_in_at', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $shifts = $query->paginate(50)->withQueryString();

        // Per-user summary for the selected period
        $summary = StaffShift::whereBetween('clocked_in_at', [$from, $to])
            ->whereNotNull('clocked_out_at')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('user_id, COUNT(*) AS shift_count, SUM(TIMESTAMPDIFF(MINUTE, clocked_in_at, clocked_out_at)) AS total_minutes')
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get();

        $staff = User::role(Roles::ALL_STAFF)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('owner.attendance.index', compact('shifts', 'summary', 'staff', 'from', 'to', 'userId'));
    }

    public function show(User $user, Request $request)
    {
        $from = Carbon::parse($request->get('from', now()->subDays(30)->startOfDay()));
        $to   = Carbon::parse($request->get('to', now()->endOfDay()));

        $shifts = StaffShift::where('user_id', $user->id)
            ->whereBetween('clocked_in_at', [$from, $to])
            ->orderBy('clocked_in_at', 'desc')
            ->get();

        $closedShifts = $shifts->filter(fn ($s) => !$s->isOpen());
        $totalShifts  = $shifts->count();
        $totalHours   = $closedShifts->sum(fn ($s) => $s->durationMinutes() ?? 0) / 60;
        $openShift    = $shifts->first(fn ($s) => $s->isOpen());

        $staff = $user;

        return view('owner.attendance.show', compact('staff', 'shifts', 'from', 'to', 'totalShifts', 'totalHours', 'openShift'));
    }
}
