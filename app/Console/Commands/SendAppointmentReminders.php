<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentBooked;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';
    protected $description = 'Send 24-hour reminder notifications to patients for upcoming appointments';

    public function handle(): int
    {
        $window_start = now()->addHours(23);
        $window_end   = now()->addHours(25);

        $appointments = Appointment::with('patient.user')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [$window_start, $window_end])
            ->where('reminder_sent', false)
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No reminders to send.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($appointments as $appointment) {
            $patient = $appointment->patient;
            if (!$patient || !$patient->user) {
                continue;
            }

            try {
                $patient->user->notify(new AppointmentBooked($appointment));
                $appointment->update([
                    'reminder_sent'    => true,
                    'reminder_sent_at' => now(),
                ]);
                $sent++;
            } catch (\Exception $e) {
                $this->warn("Failed reminder for appointment #{$appointment->id}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} reminder(s) out of {$appointments->count()} due.");
        return self::SUCCESS;
    }
}
