# Backend Software Engineer Task: Appointment Reminder System

This document outlines the development plan for building a RESTful API for an Appointment Reminder System using Laravel. It covers the core requirements, bonus features, and a phased approach to implementation.

## 1. Project Overview

The goal is to create a robust, scalable, and well-tested RESTful API that allows businesses to manage appointments and automatically send reminders to their clients. The system will handle scheduling with timezone awareness, process reminders asynchronously using queues, and support features like recurring appointments and customizable notifications.

## 2. Core Technologies

- **Backend Framework:** Laravel 10+ (PHP 8.1+)
- **API Standard:** RESTful
- **Database:** MySQL 8+ or PostgreSQL 13+
- **Authentication:** Laravel Sanctum (for token-based API authentication)
- **Asynchronous Processing:** Laravel Queues with Redis as the driver
- **Containerization:** Docker & Laravel Sail
- **Local Mail Testing:** Mailpit
- **Testing:** Pest / PHPUnit

## 3. Development Plan & Phased Implementation

### Phase 1: Project Setup & Authentication

1.  **Initialize Laravel Project:**
    -   Use Composer to create a new Laravel 10+ project.
    -   `composer create-project laravel/laravel .`

2.  **Setup Laravel Sail:**
    -   Install Sail: `php artisan sail:install`
    -   Select `mysql` and `redis` when prompted.
    -   Start the Docker containers: `./vendor/bin/sail up -d`

3.  **Database & Environment Configuration:**
    -   Configure the `.env` file. Sail automatically sets up the database connection variables (`DB_HOST`, `DB_DATABASE`, etc.).
    -   Set `QUEUE_CONNECTION=redis` to use Redis for background jobs.
    -   Set `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `MAIL_ENCRYPTION=null` to route emails to Mailpit.

4.  **Implement User Authentication:**
    -   Publish Sanctum's configuration and migration files: `sail artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
    -   Run initial migrations: `sail artisan migrate`
    -   Create API routes and controllers for user registration and login that return Sanctum API tokens upon success.
    -   **Endpoints:**
        -   `POST /api/register` (name, email, password)
        -   `POST /api/login` (email, password)
        -   `POST /api/logout` (protected by `auth:sanctum`)

### Phase 2: Core Models & Database Schema

1.  **Generate Models and Migrations:**
    -   `sail artisan make:model Client -m`
    -   `sail artisan make:model Appointment -m`
    -   `sail artisan make:model ReminderDispatch -m`

2.  **Define Database Schema (Migrations):**
    -   **`users` table:** (Provided by Laravel)
        - Add `timezone` (string, default 'UTC')
    -   **`clients` table:**
        - `id`, `timestamps`
        - `user_id` (foreign key)
        - `name` (string)
        - `email` (string)
        - `phone_number` (string, nullable)
        - `timezone` (string, default 'UTC')
    -   **`appointments` table:**
        - `id`, `timestamps`
        - `user_id` (foreign key)
        - `client_id` (foreign key)
        - `title` (string)
        - `description` (text, nullable)
        - `appointment_time` (datetime) - **Stored in UTC**
        - `timezone` (string) - The original timezone of the appointment (e.g., 'America/New_York')
    -   **`reminder_dispatches` table:**
        - `id`, `timestamps`
        - `appointment_id` (foreign key)
        - `method` (enum: 'email', 'sms')
        - `sent_at` (datetime, nullable)
        - `status` (enum: 'scheduled', 'sent', 'failed')

3.  **Define Model Relationships:**
    -   `User`: `hasMany(Client)`, `hasMany(Appointment)`
    -   `Client`: `belongsTo(User)`, `hasMany(Appointment)`
    -   `Appointment`: `belongsTo(User)`, `belongsTo(Client)`, `hasMany(ReminderDispatch)`
    -   `ReminderDispatch`: `belongsTo(Appointment)`

4.  **Run Migrations:**
    -   `sail artisan migrate`

### Phase 3: API Endpoints (CRUD Operations)

*All endpoints below should be protected by `auth:sanctum` middleware.*

1.  **Client Management:** (Users manage their own clients)
    -   `GET /api/clients`
    -   `POST /api/clients`
    -   `GET /api/clients/{client}`
    -   `PUT/PATCH /api/clients/{client}`
    -   `DELETE /api/clients/{client}`

2.  **Appointment Management:** (Users manage their own appointments)
    -   `GET /api/appointments` (with query params for filtering, e.g., `?status=past`, `?status=upcoming`)
    -   `POST /api/appointments`
    -   `GET /api/appointments/{appointment}`
    -   `PUT/PATCH /api/appointments/{appointment}`
    -   `DELETE /api/appointments/{appointment}`

3.  **Reminder Viewing:**
    -   `GET /api/reminders` (Lists all reminder dispatches for the authenticated user)
    -   `GET /api/appointments/{appointment}/reminders` (Lists reminders for a specific appointment)

### Phase 4: Reminder Scheduling, Queues, and Timezones

1.  **Create Notification and Job:**
    -   Create a Mailable/Notification for the reminder: `sail artisan make:notification AppointmentReminderNotification`
    -   Create a Job to handle sending the notification: `sail artisan make:job SendReminderJob`

2.  **Implement Job Logic (`SendReminderJob`):**
    -   The job will accept an `Appointment` instance.
    -   It will find the associated `Client` and use Laravel's Notification facade to send the `AppointmentReminderNotification`.
    -   Implement try/catch blocks to update the `ReminderDispatch` record's status to `sent` or `failed`.

3.  **Implement Scheduling Logic:**
    -   Use an `AppointmentObserver` or model events (`created`, `updated`).
    -   When an appointment is created or updated:
        1.  Delete any previously scheduled reminder jobs for this appointment to prevent duplicate notifications.
        2.  Create a new `ReminderDispatch` record with `status = 'scheduled'`.
        3.  Calculate the notification time. The `appointment_time` from the request will include a `timezone`. This will be converted to UTC for storage. The delay calculation must be precise.
        4.  **Timezone-Aware Delay Calculation:**
            ```php
            // In AppointmentObserver.php
            use Illuminate\Support\Carbon;

            $appointmentTime = Carbon::parse($appointment->appointment_time, $appointment->timezone);
            $reminderTime = $appointmentTime->subHours(1); // Using configurable offset
            
            // Dispatch job if reminder time is in the future
            if ($reminderTime->isFuture()) {
                SendReminderJob::dispatch($appointment)->delay($reminderTime);
            }
            ```
        5.  Dispatch the `SendReminderJob` with the calculated delay.

4.  **Run the Queue Worker:**
    -   `sail artisan queue:work`

### Phase 5: Implementing Bonus Features

1.  **Recurring Appointments:**
    -   **DB:** Add `is_recurring` (boolean) and `recurrence_rule` (string, e.g., `FREQ=WEEKLY;UNTIL=20241231T000000Z`) to the `appointments` table.
    -   **Logic:** Create a scheduled command (`sail artisan make:command GenerateRecurringAppointments`) to run daily. This command will find active recurring appointments and create new instances for the upcoming period based on their rule.

2.  **Custom Reminder Offsets & Preferences:**
    -   **DB:**
        -   Create a `reminders` table: `appointment_id`, `send_at` (datetime), `status`.
        -   Modify `appointments` to have a `reminder_offsets` JSON column (e.g., `['1 day', '1 hour']`).
    -   **Logic:** When an appointment is created, loop through the offsets, calculate each `send_at` time, and create multiple `Reminder` records and dispatch multiple jobs.

3.  **Appointment Status:**
    -   **DB:** Add a `status` enum (`scheduled`, `completed`, `cancelled`, `missed`) to the `appointments` table.
    -   **API:** Create a dedicated endpoint to update the status: `PATCH /api/appointments/{appointment}/status`.

4.  **Admin Panel (API-only):**
    -   **Auth:** Add an `is_admin` boolean to the `users` table. Create an `AdminMiddleware` to check this flag.
    -   **Routes:** Group admin-only endpoints under an `/api/admin` prefix protected by the `AdminMiddleware`.
    -   **Functionality:** Create admin controllers to view all system-wide appointments, reminders, and user statistics.

5.  **Retry Logic:**
    -   **Queue Worker:** Run the queue worker with flags for retries and backoff: `sail artisan queue:work --tries=3 --backoff=60`.
    -   **Monitoring:** Set up the failed jobs table (`sail artisan queue:failed-table` and `migrate`) to inspect and retry jobs manually if needed.

6.  **Analytics:**
    -   **API:** Create a `/api/analytics` endpoint that returns key metrics by querying the existing tables.
    -   **Metrics:** `sent_reminders`, `failed_reminders`, `upcoming_reminders`, `appointments_by_status`. The endpoint can accept `period` (day, week, month) as a query parameter.

7.  **Testing (Pest/PHPUnit):**
    -   **Unit Tests:** For critical business logic (e.g., timezone conversions, recurrence rule parsing).
    -   **Feature Tests:** For all API endpoints. Use `actingAs()` for authentication, `assertStatus()`, `assertJson()` for response validation, and `Queue::fake()` to assert that jobs are dispatched correctly without actually executing them.

## 4. Documentation & Deployment

1.  **README.md:**
    -   Provide clear, step-by-step instructions on how to clone, install, and run the project using Laravel Sail.
    -   Include instructions for running migrations, seeders (if any), and the queue worker.
    -   Provide an `.env.example` file with all necessary environment variables.

2.  **GitHub Repository:**
    -   Push the final code to a public GitHub repository.
    -   Maintain a clean and descriptive Git history with atomic commits. 