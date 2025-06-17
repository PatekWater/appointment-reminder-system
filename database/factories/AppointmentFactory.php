<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appointmentTime = $this->faker->dateTimeBetween('now', '+30 days');
        $timezone = $this->faker->randomElement([
            'UTC',
            'America/New_York',
            'America/Los_Angeles',
            'Europe/London',
            'Asia/Tokyo',
            'Australia/Sydney'
        ]);

        // Convert to UTC for storage
        $appointmentTimeUtc = Carbon::parse($appointmentTime, $timezone)->utc();

        return [
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
            'title' => $this->faker->randomElement([
                'Dental Checkup',
                'Medical Consultation',
                'Business Meeting',
                'Doctor Appointment',
                'Team Meeting',
                'Project Review',
                'Client Call',
                'Training Session',
                'Performance Review',
                'Health Screening'
            ]),
            'description' => $this->faker->optional()->sentence(),
            'appointment_time' => $appointmentTimeUtc,
            'timezone' => $timezone,
            'status' => $this->faker->randomElement(['scheduled', 'completed', 'cancelled', 'missed']),
            'is_recurring' => false,
            'recurrence_rule' => null,
            'parent_appointment_id' => null,
            'reminder_offsets' => null,
        ];
    }

    /**
     * Create a scheduled appointment.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
        ]);
    }

    /**
     * Create a completed appointment.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Create a cancelled appointment.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Create a recurring appointment.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurrence_rule' => $this->faker->randomElement([
                'FREQ=WEEKLY;UNTIL=' . Carbon::now()->addMonths(6)->format('Ymd\THis\Z'),
                'FREQ=MONTHLY;UNTIL=' . Carbon::now()->addMonths(12)->format('Ymd\THis\Z'),
                'FREQ=DAILY;UNTIL=' . Carbon::now()->addWeeks(4)->format('Ymd\THis\Z'),
            ]),
        ]);
    }

    /**
     * Create an appointment with custom reminder offsets.
     */
    public function withCustomReminders(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_offsets' => $this->faker->randomElement([
                ['1 hour'],
                ['1 day', '1 hour'],
                ['1 week', '1 day', '2 hours'],
                ['30 minutes'],
                ['2 days', '4 hours', '30 minutes'],
            ]),
        ]);
    }

    /**
     * Create an upcoming appointment.
     */
    public function upcoming(): static
    {
        $appointmentTime = $this->faker->dateTimeBetween('+1 hour', '+30 days');
        $timezone = 'UTC';
        $appointmentTimeUtc = Carbon::parse($appointmentTime, $timezone)->utc();

        return $this->state(fn (array $attributes) => [
            'appointment_time' => $appointmentTimeUtc,
            'timezone' => $timezone,
            'status' => 'scheduled',
        ]);
    }

    /**
     * Create a past appointment.
     */
    public function past(): static
    {
        $appointmentTime = $this->faker->dateTimeBetween('-30 days', '-1 hour');
        $timezone = 'UTC';
        $appointmentTimeUtc = Carbon::parse($appointmentTime, $timezone)->utc();

        return $this->state(fn (array $attributes) => [
            'appointment_time' => $appointmentTimeUtc,
            'timezone' => $timezone,
            'status' => $this->faker->randomElement(['completed', 'missed']),
        ]);
    }
}
