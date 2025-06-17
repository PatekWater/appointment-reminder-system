<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Client;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TestReminderSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-reminder-system {--user-id= : ID of the user to create test data for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test appointments to verify the reminder system is working';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        
        if (!$userId) {
            $this->error('Please provide a user ID using --user-id option');
            return Command::FAILURE;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return Command::FAILURE;
        }

        $this->info("🧪 Creating test appointments for user: {$user->name} ({$user->email})");
        $this->newLine();

        // Create a test client if one doesn't exist
        $client = $user->clients()->first();
        if (!$client) {
            $client = $user->clients()->create([
                'name' => 'Test Client',
                'email' => 'testclient@example.com',
                'phone_number' => '+1234567890',
                'timezone' => $user->timezone ?? 'UTC',
            ]);
            $this->info("📝 Created test client: {$client->name}");
        } else {
            $this->info("📝 Using existing client: {$client->name}");
        }

        // Test 1: Simple appointment with default 1-hour reminder
        $this->createTestAppointment($user, $client, [
            'title' => 'Simple Test Appointment (1 hour reminder)',
            'description' => 'This appointment uses the default 1-hour reminder system.',
            'appointment_time' => Carbon::now()->addHours(2)->format('Y-m-d H:i:s'),
            'timezone' => $user->timezone ?? 'UTC',
        ]);

        // Test 2: Appointment with custom reminder offsets
        $this->createTestAppointment($user, $client, [
            'title' => 'Custom Reminders Test Appointment',
            'description' => 'This appointment uses custom reminder offsets: 1 day, 2 hours, and 15 minutes before.',
            'appointment_time' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'timezone' => $user->timezone ?? 'UTC',
            'reminder_offsets' => ['1 day', '2 hours', '15 minutes'],
        ]);

        // Test 3: Recurring appointment
        $this->createTestAppointment($user, $client, [
            'title' => 'Weekly Recurring Test Meeting',
            'description' => 'This is a weekly recurring appointment for testing.',
            'appointment_time' => Carbon::now()->addDays(3)->format('Y-m-d H:i:s'),
            'timezone' => $user->timezone ?? 'UTC',
            'is_recurring' => true,
            'recurrence_rule' => 'FREQ=WEEKLY;UNTIL=' . Carbon::now()->addMonths(2)->format('Ymd\THis\Z'),
            'reminder_offsets' => ['1 hour', '30 minutes'],
        ]);

        $this->newLine();
        $this->info('✅ Test appointments created successfully!');
        $this->info('📧 Check your email (or Mailpit at http://localhost:8025) for reminder notifications.');
        $this->info('🔄 To generate recurring appointment instances, run: php artisan app:generate-recurring-appointments');
        
        return Command::SUCCESS;
    }

    /**
     * Create a test appointment with the given data.
     */
    private function createTestAppointment(User $user, Client $client, array $data): void
    {
        $appointmentTime = Carbon::parse($data['appointment_time'], $data['timezone'])->utc();
        
        $appointmentData = [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'appointment_time' => $appointmentTime,
            'timezone' => $data['timezone'],
            'status' => 'scheduled',
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_rule' => $data['recurrence_rule'] ?? null,
            'reminder_offsets' => $data['reminder_offsets'] ?? null,
        ];

        $appointment = Appointment::create($appointmentData);
        
        $typeInfo = '';
        if ($appointment->is_recurring) {
            $typeInfo = ' (recurring)';
        }
        if (!empty($appointment->reminder_offsets)) {
            $typeInfo .= ' with custom reminders: ' . implode(', ', $appointment->reminder_offsets);
        }

        $this->info("   ✓ Created: {$appointment->title}{$typeInfo}");
        $this->info("     └── Scheduled for: " . Carbon::parse($appointment->appointment_time)->setTimezone($appointment->timezone)->format('M j, Y g:i A T'));
    }
}
