<?php

namespace Tests\Feature;

use App\Jobs\SendReminderJob;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentReminderSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Client $client;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'timezone' => 'UTC',
            'is_admin' => false,
        ]);

        // Generate authentication token
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Create a test client
        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'timezone' => 'UTC',
        ]);
    }

    public function test_user_registration(): void
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'timezone' => 'America/New_York',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'user' => ['id', 'name', 'email', 'timezone'],
                         'token',
                         'token_type'
                     ]
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);
    }

    public function test_user_login(): void
    {
        $loginData = [
            'email' => $this->user->email,
            'password' => 'password', // Default factory password
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'user' => ['id', 'name', 'email', 'timezone'],
                         'token',
                         'token_type'
                     ]
                 ]);
    }

    public function test_create_client(): void
    {
        $clientData = [
            'name' => 'New Client',
            'email' => 'newclient@example.com',
            'phone_number' => '+1234567890',
            'timezone' => 'America/New_York',
        ];

        $response = $this->withToken($this->token)
                         ->postJson('/api/clients', $clientData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => ['id', 'name', 'email', 'phone_number', 'timezone']
                 ]);

        $this->assertDatabaseHas('clients', [
            'name' => 'New Client',
            'email' => 'newclient@example.com',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_create_simple_appointment(): void
    {
        Queue::fake();

        $appointmentData = [
            'client_id' => $this->client->id,
            'title' => 'Dental Checkup',
            'description' => 'Regular dental examination',
            'appointment_time' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'timezone' => 'UTC',
        ];

        $response = $this->withToken($this->token)
                         ->postJson('/api/appointments', $appointmentData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id', 'title', 'description', 'appointment_time', 'timezone',
                         'client' => ['id', 'name', 'email']
                     ]
                 ]);

        $this->assertDatabaseHas('appointments', [
            'title' => 'Dental Checkup',
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        // Assert that a reminder job was queued
        Queue::assertPushed(SendReminderJob::class);
    }

    public function test_create_appointment_with_custom_reminders(): void
    {
        Queue::fake();

        $appointmentData = [
            'client_id' => $this->client->id,
            'title' => 'Important Meeting',
            'appointment_time' => Carbon::now()->addDays(3)->format('Y-m-d H:i:s'),
            'timezone' => 'UTC',
            'reminder_offsets' => ['1 day', '2 hours', '15 minutes'],
        ];

        $response = $this->withToken($this->token)
                         ->postJson('/api/appointments', $appointmentData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'reminder_offsets' => ['1 day', '2 hours', '15 minutes']
                     ]
                 ]);

        // Assert that custom reminders were created
        $appointment = Appointment::where('title', 'Important Meeting')->first();
        $this->assertCount(3, $appointment->appointmentReminders);
    }

    public function test_create_recurring_appointment(): void
    {
        $appointmentData = [
            'client_id' => $this->client->id,
            'title' => 'Weekly Team Meeting',
            'appointment_time' => Carbon::now()->addWeek()->format('Y-m-d H:i:s'),
            'timezone' => 'UTC',
            'is_recurring' => true,
            'recurrence_rule' => 'FREQ=WEEKLY;UNTIL=' . Carbon::now()->addMonths(2)->format('Ymd\THis\Z'),
        ];

        $response = $this->withToken($this->token)
                         ->postJson('/api/appointments', $appointmentData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Recurring appointment created successfully',
                     'data' => [
                         'is_recurring' => true,
                         'recurrence_rule' => $appointmentData['recurrence_rule']
                     ]
                 ]);
    }

    public function test_update_appointment_status(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status' => 'scheduled',
        ]);

        $response = $this->withToken($this->token)
                         ->patchJson("/api/appointments/{$appointment->id}/status", [
                             'status' => 'completed'
                         ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => ['status' => 'completed']
                 ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    public function test_get_user_appointments(): void
    {
        // Create multiple appointments
        Appointment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->withToken($this->token)
                         ->getJson('/api/appointments');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => ['id', 'title', 'appointment_time', 'client']
                     ]
                 ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_reminders(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->withToken($this->token)
                         ->getJson('/api/reminders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data'
                 ]);
    }

    public function test_analytics_endpoint(): void
    {
        // Create some test data
        Appointment::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->withToken($this->token)
                         ->getJson('/api/analytics?period=month');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'period',
                         'reminders',
                         'appointments',
                         'trends'
                     ]
                 ]);
    }

    public function test_unauthorized_access_denied(): void
    {
        $response = $this->getJson('/api/appointments');
        $response->assertStatus(401);
    }

    public function test_admin_access_for_regular_user(): void
    {
        $response = $this->withToken($this->token)
                         ->getJson('/api/admin/appointments');

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Access denied. Admin privileges required.'
                 ]);
    }

    public function test_admin_endpoints_for_admin_user(): void
    {
        // Make user an admin
        $this->user->update(['is_admin' => true]);
        $adminToken = $this->user->createToken('admin-token')->plainTextToken;

        $response = $this->withToken($adminToken)
                         ->getJson('/api/admin/appointments');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data'
                 ]);
    }

    public function test_appointment_validation(): void
    {
        $invalidData = [
            'client_id' => 999, // Non-existent client
            'title' => '', // Empty title
            'appointment_time' => 'invalid-date',
            'timezone' => 'Invalid/Timezone',
        ];

        $response = $this->withToken($this->token)
                         ->postJson('/api/appointments', $invalidData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['client_id', 'title', 'appointment_time', 'timezone']);
    }

    public function test_recurring_appointment_validation(): void
    {
        $invalidRecurringData = [
            'client_id' => $this->client->id,
            'title' => 'Test Recurring',
            'appointment_time' => Carbon::now()->addWeek()->format('Y-m-d H:i:s'),
            'timezone' => 'UTC',
            'is_recurring' => true,
            'recurrence_rule' => 'INVALID_RULE',
        ];

        $response = $this->withToken($this->token)
                         ->postJson('/api/appointments', $invalidRecurringData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['recurrence_rule']);
    }
} 