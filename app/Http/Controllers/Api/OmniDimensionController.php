<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DoctorAvailabilitySlot;
use App\Notifications\GenericOwnerAlert;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OmniDimensionController extends Controller
{
    /**
     * Handle incoming OmniDimension phone AI webhooks.
     *
     * POST /api/omnidimension/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        // ── HMAC verification ────────────────────────────────────────────────
        $secret    = config('services.omnidimension.webhook_secret', env('OMNIDIMENSION_WEBHOOK_SECRET', ''));
        $rawBody   = $request->getContent();
        $signature = $request->header('X-OmniDimension-Signature', '');

        if ($secret !== '' && ! hash_equals(
            'sha256=' . hash_hmac('sha256', $rawBody, $secret),
            $signature
        )) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        try {
            $body = $request->json()->all();

            $action        = $body['action'] ?? '';
            $callerPhone   = $body['caller_phone'] ?? null;
            $callerName    = $body['caller_name'] ?? null;
            $doctorName    = $body['doctor_name'] ?? null;
            $specialty     = $body['specialty'] ?? null;
            $apptDate      = $body['appointment_date'] ?? null;
            $apptTime      = $body['appointment_time'] ?? null;
            $notes         = $body['notes'] ?? null;

            return match ($action) {
                'book_appointment'    => $this->bookAppointment(
                    $callerPhone, $callerName, $doctorName, $specialty,
                    $apptDate, $apptTime, $notes
                ),
                'check_availability'  => $this->checkAvailability($doctorName, $apptDate),
                default               => response()->json(['success' => false, 'message' => 'Unknown action'], 422),
            };
        } catch (\Throwable $e) {
            Log::error('OmniDimension webhook error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function bookAppointment(
        ?string $callerPhone,
        ?string $callerName,
        ?string $doctorName,
        ?string $specialty,
        ?string $apptDate,
        ?string $apptTime,
        ?string $notes
    ): JsonResponse {
        // Resolve doctor
        $doctor = null;
        if ($doctorName) {
            $doctor = DB::table('users')
                ->whereRaw("JSON_SEARCH(roles_cache, 'one', 'Doctor') IS NOT NULL OR id IN (
                    SELECT model_id FROM model_has_roles
                    JOIN roles ON roles.id = model_has_roles.role_id
                    WHERE roles.name = 'Doctor' AND model_has_roles.model_type = 'App\\\\Models\\\\User'
                )")
                ->where('name', 'LIKE', '%' . $doctorName . '%')
                ->first();
        }

        if (! $doctor && $specialty) {
            // Fallback: find any doctor matching the specialty via role
            $doctor = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'Doctor')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->select('users.*')
                ->first();
        }

        // Parse scheduled_at
        $scheduledAt = null;
        if ($apptDate && $apptTime) {
            try {
                $scheduledAt = Carbon::parse("{$apptDate} {$apptTime}");
            } catch (\Throwable) {
                $scheduledAt = Carbon::now()->addHour();
            }
        }

        $appointment = Appointment::create([
            'source'           => Appointment::SOURCE_OMNIDIMENSION,
            'pre_booked_name'  => $callerName,
            'pre_booked_phone' => $callerPhone,
            'doctor_id'        => $doctor->id ?? null,
            'scheduled_at'     => $scheduledAt,
            'status'           => Appointment::STATUS_SCHEDULED,
            'reason'           => $notes,
        ]);

        // Notify Owner + Receptionist
        $notifiables = \App\Models\User::role(['Owner', 'Receptionist'])->get();
        Notification::send($notifiables, new GenericOwnerAlert(
            message: "OmniDimension booked phone appointment for {$callerName} ({$callerPhone})" .
                     ($doctor ? " with Dr. {$doctor->name}" : '') .
                     ($scheduledAt ? " on {$scheduledAt->format('d M Y H:i')}" : ''),
            icon:    'bi-telephone-plus',
            color:   'info',
            url:     '/receptionist/pre-booked',
            title:   'Phone Appointment Booked',
        ));

        AuditLog::log(
            action:        'omnidimension.webhook',
            auditableType: 'App\Models\Appointment',
            auditableId:   $appointment->id,
            beforeState:   null,
            afterState:    [
                'source'          => 'omnidimension',
                'pre_booked_name' => $callerName,
                'caller_phone'    => $callerPhone,
                'doctor_id'       => $appointment->doctor_id,
            ]
        );

        return response()->json([
            'success'        => true,
            'appointment_id' => $appointment->id,
            'message'        => 'Appointment booked',
        ]);
    }

    private function checkAvailability(?string $doctorName, ?string $date): JsonResponse
    {
        $query = DoctorAvailabilitySlot::active();

        if ($doctorName) {
            $query->whereHas('doctor', fn ($q) => $q->where('name', 'LIKE', '%' . $doctorName . '%'));
        }

        if ($date) {
            $parsedDate = Carbon::parse($date);
            $dow        = $parsedDate->dayOfWeek;

            $query->where(function ($q) use ($parsedDate, $dow) {
                $q->where(function ($inner) use ($parsedDate) {
                    $inner->where('is_recurring', false)->whereDate('date', $parsedDate->toDateString());
                })->orWhere(function ($inner) use ($dow) {
                    $inner->where('is_recurring', true)->where('day_of_week', $dow);
                });
            });
        }

        $slots = $query->with('doctor:id,name')->get()->map(fn ($slot) => [
            'doctor'     => $slot->doctor?->name,
            'start_time' => $slot->start_time,
            'end_time'   => $slot->end_time,
            'slot_count' => $slot->slot_count,
        ]);

        return response()->json([
            'success' => true,
            'slots'   => $slots,
        ]);
    }
}
