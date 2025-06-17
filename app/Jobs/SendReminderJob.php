<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\ReminderDispatch;
use App\Models\AppointmentReminder;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendReminderJob implements ShouldQueue
{
    use Queueable;

    protected $appointment;
    protected $reminderDispatch;
    protected $appointmentReminder;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Appointment $appointment, 
        ReminderDispatch $reminderDispatch = null,
        AppointmentReminder $appointmentReminder = null
    ) {
        $this->appointment = $appointment;
        $this->reminderDispatch = $reminderDispatch;
        $this->appointmentReminder = $appointmentReminder;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Ensure the appointment still exists and hasn't been deleted
            if (!$this->appointment->exists) {
                Log::info('Appointment no longer exists, skipping reminder', [
                    'appointment_id' => $this->appointment->id
                ]);
                return;
            }

            // Load the client relationship
            $this->appointment->load('client');
            
            if (!$this->appointment->client) {
                Log::error('Client not found for appointment', [
                    'appointment_id' => $this->appointment->id
                ]);
                
                $this->markReminderAsFailed();
                return;
            }

            // Create ReminderDispatch record if we only have AppointmentReminder
            if (!$this->reminderDispatch && $this->appointmentReminder) {
                $this->reminderDispatch = ReminderDispatch::create([
                    'appointment_id' => $this->appointment->id,
                    'method' => $this->appointmentReminder->method ?? 'email',
                    'status' => 'scheduled'
                ]);
            }

            // Create AppointmentReminder record if we only have ReminderDispatch (backward compatibility)
            if (!$this->appointmentReminder && $this->reminderDispatch) {
                // This is for backward compatibility with old reminder system
            }

            // Send the notification
            Notification::send(
                $this->appointment->client,
                new AppointmentReminderNotification($this->appointment)
            );

            // Update reminder status to sent
            $this->markReminderAsSent();

            $logContext = [
                'appointment_id' => $this->appointment->id,
                'client_id' => $this->appointment->client->id,
                'client_email' => $this->appointment->client->email
            ];

            if ($this->appointmentReminder) {
                $logContext['reminder_offset'] = $this->appointmentReminder->offset_value;
            }

            Log::info('Appointment reminder sent successfully', $logContext);

        } catch (\Exception $e) {
            Log::error('Failed to send appointment reminder', [
                'appointment_id' => $this->appointment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark reminder as failed
            $this->markReminderAsFailed();

            // Re-throw the exception to trigger job retry mechanism
            throw $e;
        }
    }

    /**
     * Mark reminder(s) as sent.
     */
    private function markReminderAsSent(): void
    {
        $sentAt = now();

        if ($this->reminderDispatch) {
            $this->reminderDispatch->update([
                'status' => 'sent',
                'sent_at' => $sentAt
            ]);
        }

        if ($this->appointmentReminder) {
            $this->appointmentReminder->update([
                'status' => 'sent',
                'sent_at' => $sentAt
            ]);
        }
    }

    /**
     * Mark reminder(s) as failed.
     */
    private function markReminderAsFailed(): void
    {
        $sentAt = now();

        if ($this->reminderDispatch) {
            $this->reminderDispatch->update([
                'status' => 'failed',
                'sent_at' => $sentAt
            ]);
        }

        if ($this->appointmentReminder) {
            $this->appointmentReminder->update([
                'status' => 'failed',
                'sent_at' => $sentAt
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendReminderJob failed permanently', [
            'appointment_id' => $this->appointment->id,
            'error' => $exception->getMessage()
        ]);

        // Mark as failed if we have reminder records
        $this->markReminderAsFailed();
    }
}
