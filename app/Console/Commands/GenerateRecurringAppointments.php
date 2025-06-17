<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRecurringAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-recurring-appointments {--days=30 : Number of days ahead to generate appointments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring appointment instances based on recurrence rules';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysAhead = $this->option('days');
        $this->info("🔄 Generating recurring appointments for the next {$daysAhead} days...");
        $this->newLine();

        // Get all master recurring appointments (not instances)
        $recurringAppointments = Appointment::masterRecurring()->get();

        if ($recurringAppointments->isEmpty()) {
            $this->info('📅 No recurring appointments found.');
            return Command::SUCCESS;
        }

        $totalGenerated = 0;

        foreach ($recurringAppointments as $masterAppointment) {
            $this->info("🔄 Processing: {$masterAppointment->title}");
            
            $generated = $this->generateInstancesForAppointment($masterAppointment, $daysAhead);
            $totalGenerated += $generated;
            
            $this->info("   └── Generated {$generated} instances");
        }

        $this->newLine();
        $this->info("✅ Successfully generated {$totalGenerated} recurring appointment instances!");
        
        return Command::SUCCESS;
    }

    /**
     * Generate instances for a specific recurring appointment.
     */
    private function generateInstancesForAppointment(Appointment $masterAppointment, int $daysAhead): int
    {
        $endDate = Carbon::now()->addDays($daysAhead);
        $generated = 0;

        // Parse the recurrence rule
        $rule = $this->parseRecurrenceRule($masterAppointment->recurrence_rule);
        
        if (!$rule) {
            $this->warn("   └── Invalid recurrence rule: {$masterAppointment->recurrence_rule}");
            return 0;
        }

        // Start from the master appointment time
        $currentDate = Carbon::parse($masterAppointment->appointment_time);
        
        // Generate instances
        while ($currentDate->lte($endDate) && (!isset($rule['until']) || $currentDate->lte($rule['until']))) {
            // Skip if this instance already exists
            if (!$this->instanceExists($masterAppointment, $currentDate)) {
                $this->createAppointmentInstance($masterAppointment, $currentDate);
                $generated++;
            }

            // Move to next occurrence based on frequency
            $currentDate = $this->getNextOccurrence($currentDate, $rule);
            
            // Safety check to prevent infinite loops
            if ($generated > 100) {
                $this->warn("   └── Safety limit reached (100 instances). Stopping generation.");
                break;
            }
        }

        return $generated;
    }

    /**
     * Parse basic RRULE format.
     * Supports: FREQ=WEEKLY;UNTIL=20241231T000000Z
     */
    private function parseRecurrenceRule(?string $rule): ?array
    {
        if (!$rule) {
            return null;
        }

        $parts = explode(';', $rule);
        $parsed = [];

        foreach ($parts as $part) {
            if (strpos($part, '=') === false) continue;
            
            [$key, $value] = explode('=', $part, 2);
            
            switch ($key) {
                case 'FREQ':
                    $parsed['frequency'] = strtolower($value);
                    break;
                case 'UNTIL':
                    $parsed['until'] = Carbon::parse($value);
                    break;
                case 'INTERVAL':
                    $parsed['interval'] = (int) $value;
                    break;
            }
        }

        return $parsed['frequency'] ?? null ? $parsed : null;
    }

    /**
     * Get the next occurrence based on frequency.
     */
    private function getNextOccurrence(Carbon $date, array $rule): Carbon
    {
        $interval = $rule['interval'] ?? 1;
        
        return match($rule['frequency']) {
            'daily' => $date->copy()->addDays($interval),
            'weekly' => $date->copy()->addWeeks($interval),
            'monthly' => $date->copy()->addMonths($interval),
            'yearly' => $date->copy()->addYears($interval),
            default => $date->copy()->addWeeks($interval) // Default to weekly
        };
    }

    /**
     * Check if an appointment instance already exists.
     */
    private function instanceExists(Appointment $masterAppointment, Carbon $date): bool
    {
        return Appointment::where('parent_appointment_id', $masterAppointment->id)
            ->where('appointment_time', $date->utc())
            ->exists();
    }

    /**
     * Create a new appointment instance.
     */
    private function createAppointmentInstance(Appointment $masterAppointment, Carbon $date): void
    {
        Appointment::create([
            'user_id' => $masterAppointment->user_id,
            'client_id' => $masterAppointment->client_id,
            'title' => $masterAppointment->title,
            'description' => $masterAppointment->description,
            'appointment_time' => $date->utc(),
            'timezone' => $masterAppointment->timezone,
            'status' => 'scheduled',
            'is_recurring' => false, // Instances are not recurring themselves
            'parent_appointment_id' => $masterAppointment->id,
            'reminder_offsets' => $masterAppointment->reminder_offsets,
        ]);
    }
}
