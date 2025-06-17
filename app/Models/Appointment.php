<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'client_id',
        'title',
        'description',
        'appointment_time',
        'timezone',
        'status',
        'is_recurring',
        'recurrence_rule',
        'parent_appointment_id',
        'reminder_offsets',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'appointment_time' => 'datetime',
        'is_recurring' => 'boolean',
        'reminder_offsets' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    
    /**
     * Get the user that owns the appointment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the client associated with the appointment.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the reminder dispatches for the appointment.
     */
    public function reminderDispatches()
    {
        return $this->hasMany(ReminderDispatch::class);
    }

    /**
     * Get the custom appointment reminders.
     */
    public function appointmentReminders()
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    /**
     * Get the parent appointment (for recurring instances).
     */
    public function parentAppointment()
    {
        return $this->belongsTo(Appointment::class, 'parent_appointment_id');
    }

    /**
     * Get the child appointments (recurring instances).
     */
    public function childAppointments()
    {
        return $this->hasMany(Appointment::class, 'parent_appointment_id');
    }

    /**
     * Scope methods
     */
    
    /**
     * Scope a query to only include upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_time', '>', now());
    }

    /**
     * Scope a query to only include past appointments.
     */
    public function scopePast($query)
    {
        return $query->where('appointment_time', '<', now());
    }

    /**
     * Scope a query by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include recurring appointments.
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope a query to only include master recurring appointments (not instances).
     */
    public function scopeMasterRecurring($query)
    {
        return $query->where('is_recurring', true)->whereNull('parent_appointment_id');
    }
}
