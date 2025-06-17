<?php

namespace App\Observers;

use App\Jobs\SendReminderJob;
use App\Models\Appointment;
use App\Models\ReminderDispatch;
use App\Models\AppointmentReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentObserver
{
    /**
     * Handle the Appointment "created" event.
     */
    public function created(Appointment $appointment): void
    {
        $this->scheduleReminders($appointment);
    }

    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        $this->scheduleReminders($appointment);
    }

    /**
     * Handle the Appointment "deleting" event.
     */
    public function deleting(Appointment $appointment): void
    {
        // Delete any scheduled reminder dispatches when appointment is deleted
        $appointment->reminderDispatches()->delete();
        $appointment->appointmentReminders()->delete();
        
        Log::info('Deleted reminders for appointment', [
            'appointment_id' => $appointment->id
        ]);
    }

    /**
     * Schedule reminders for the given appointment.
     * Supports both single default reminder and custom reminder offsets.
     */
    protected function scheduleReminders(Appointment $appointment): void
    {
        try {
            // Delete any previously scheduled reminders for this appointment
            $appointment->reminderDispatches()
                ->where('status', 'scheduled')
                ->delete();
            
            $appointment->appointmentReminders()
                ->where('status', 'scheduled')
                ->delete();

            $appointmentTime = Carbon::parse($appointment->appointment_time, $appointment->timezone);

            // Check if custom reminder offsets are defined
            if (!empty($appointment->reminder_offsets) && is_array($appointment->reminder_offsets)) {
                // Create multiple reminders based on custom offsets
                foreach ($appointment->reminder_offsets as $offset) {
                    $this->createCustomReminder($appointment, $appointmentTime, $offset);
                }
            } else {
                // Create default 1-hour reminder using ReminderDispatch (legacy method)
                $this->createDefaultReminder($appointment, $appointmentTime);
            }

        } catch (\Exception $e) {
            Log::error('Failed to schedule appointment reminders', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create a custom reminder based on offset string (e.g., '1 day', '2 hours', '30 minutes').
     */
    protected function createCustomReminder(Appointment $appointment, Carbon $appointmentTime, string $offset): void
    {
        try {
            // Parse the offset string and calculate reminder time
            $reminderTime = $this->calculateReminderTime($appointmentTime, $offset);
            
            if ($reminderTime->isFuture()) {
                // Create AppointmentReminder record
                $reminder = AppointmentReminder::create([
                    'appointment_id' => $appointment->id,
                    'send_at' => $reminderTime->utc(),
                    'method' => 'email',
                    'status' => 'scheduled',
                    'offset_value' => $offset,
                ]);

                // Schedule the job
                SendReminderJob::dispatch($appointment, null, $reminder)->delay($reminderTime);
                
                Log::info('Scheduled custom appointment reminder', [
                    'appointment_id' => $appointment->id,
                    'offset' => $offset,
                    'reminder_time' => $reminderTime->toDateTimeString(),
                    'timezone' => $appointment->timezone
                ]);
            } else {
                Log::warning('Custom reminder time has passed', [
                    'appointment_id' => $appointment->id,
                    'offset' => $offset,
                    'reminder_time' => $reminderTime->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create custom reminder', [
                'appointment_id' => $appointment->id,
                'offset' => $offset,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create default 1-hour reminder using ReminderDispatch (backward compatibility).
     */
    protected function createDefaultReminder(Appointment $appointment, Carbon $appointmentTime): void
    {
        $reminderTime = $appointmentTime->copy()->subHours(1);
        
        if ($reminderTime->isFuture()) {
            $reminderDispatch = ReminderDispatch::create([
                'appointment_id' => $appointment->id,
                'method' => 'email',
                'status' => 'scheduled'
            ]);

            SendReminderJob::dispatch($appointment, $reminderDispatch)->delay($reminderTime);
            
            Log::info('Scheduled default appointment reminder', [
                'appointment_id' => $appointment->id,
                'reminder_time' => $reminderTime->toDateTimeString(),
                'timezone' => $appointment->timezone
            ]);
        } else {
            $reminderDispatch = ReminderDispatch::create([
                'appointment_id' => $appointment->id,
                'method' => 'email',
                'status' => 'failed'
            ]);
            
            Log::warning('Default reminder time has passed', [
                'appointment_id' => $appointment->id,
                'reminder_time' => $reminderTime->toDateTimeString()
            ]);
        }
    }

    /**
     * Parse offset string and calculate reminder time.
     * Supports formats like: '1 day', '2 hours', '30 minutes', '1 week'
     */
    protected function calculateReminderTime(Carbon $appointmentTime, string $offset): Carbon
    {
        $offset = trim(strtolower($offset));
        
        // Parse the offset string (e.g., "1 day", "2 hours", "30 minutes")
        if (preg_match('/^(\d+)\s+(minute|minutes|hour|hours|day|days|week|weeks)$/', $offset, $matches)) {
            $amount = (int) $matches[1];
            $unit = $matches[2];
            
            // Normalize unit to singular form
            $unit = rtrim($unit, 's');
            
            return match($unit) {
                'minute' => $appointmentTime->copy()->subMinutes($amount),
                'hour' => $appointmentTime->copy()->subHours($amount),
                'day' => $appointmentTime->copy()->subDays($amount),
                'week' => $appointmentTime->copy()->subWeeks($amount),
                default => $appointmentTime->copy()->subHours(1) // fallback to 1 hour
            };
        }
        
        // Fallback to 1 hour if parsing fails
        Log::warning('Invalid reminder offset format, using 1 hour default', [
            'offset' => $offset
        ]);
        
        return $appointmentTime->copy()->subHours(1);
    }
}
