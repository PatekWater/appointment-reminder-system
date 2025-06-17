<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $appointment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Convert appointment time to client's timezone for display
        $appointmentTime = Carbon::parse($this->appointment->appointment_time)
            ->setTimezone($this->appointment->timezone);

        $formattedDate = $appointmentTime->format('l, F j, Y');
        $formattedTime = $appointmentTime->format('g:i A T');

        return (new MailMessage)
            ->subject('Appointment Reminder: ' . $this->appointment->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('This is a friendly reminder about your upcoming appointment.')
            ->line('**Appointment Details:**')
            ->line('**Title:** ' . $this->appointment->title)
            ->line('**Date:** ' . $formattedDate)
            ->line('**Time:** ' . $formattedTime)
            ->when($this->appointment->description, function ($message) {
                return $message->line('**Description:** ' . $this->appointment->description);
            })
            ->line('We look forward to seeing you!')
            ->action('View Appointment Details', url('/'))
            ->line('If you need to reschedule or cancel, please contact us as soon as possible.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'appointment_title' => $this->appointment->title,
            'appointment_time' => $this->appointment->appointment_time,
            'client_name' => $notifiable->name,
        ];
    }
}
