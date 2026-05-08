<?php

namespace App\Http\Controllers;

use App\Models\StaffShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function clockIn(Request $request): JsonResponse
    {
        $user = Auth::user();

        $openShift = StaffShift::where('user_id', $user->id)->today()->open()->first();
        if ($openShift) {
            return response()->json([
                'status'        => 'already_clocked_in',
                'clocked_in_at' => $openShift->clocked_in_at->toTimeString(),
            ], 409);
        }

        $shift = StaffShift::create([
            'user_id'       => $user->id,
            'clocked_in_at' => now(),
            'clocked_in_ip' => $request->ip(),
        ]);

        return response()->json([
            'status'        => 'clocked_in',
            'clocked_in_at' => $shift->clocked_in_at->toTimeString(),
        ]);
    }

    public function clockOut(): JsonResponse
    {
        $user = Auth::user();

        $shift = StaffShift::where('user_id', $user->id)->today()->open()->first();
        if (!$shift) {
            return response()->json(['status' => 'not_clocked_in'], 404);
        }

        $shift->update(['clocked_out_at' => now()]);

        return response()->json([
            'status'         => 'clocked_out',
            'duration_hours' => round($shift->durationMinutes() / 60, 2),
        ]);
    }

    public function status(): JsonResponse
    {
        $user  = Auth::user();
        $shift = StaffShift::where('user_id', $user->id)->today()->open()->first();

        return response()->json([
            'open'          => (bool) $shift,
            'clocked_in_at' => $shift?->clocked_in_at->toTimeString(),
        ]);
    }
}
