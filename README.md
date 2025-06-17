# Appointment Reminder System

A robust, scalable RESTful API for managing appointments and automatically sending reminders to clients. Built with Laravel 12+ and featuring timezone awareness, asynchronous processing, recurring appointments, and customizable notifications.

## Features

### Core Features
- **User Authentication** - Token-based API authentication using Laravel Sanctum
- **Client Management** - CRUD operations for managing clients
- **Appointment Management** - Schedule, update, and manage appointments with timezone support
- **Automated Reminders** - Asynchronous reminder notifications via email
- **Timezone Awareness** - Proper handling of different timezones for global use

### Bonus Features
- **Recurring Appointments** - Support for weekly, monthly, and yearly recurring appointments
- **Custom Reminder Offsets** - Configure multiple reminders (e.g., 1 day, 2 hours, 15 minutes before)
- **Appointment Status Tracking** - Track appointments as scheduled, completed, cancelled, or missed
- **Admin Panel API** - Administrative endpoints for system-wide management
- **Analytics** - Comprehensive analytics for appointments and reminders
- **Retry Logic** - Automatic retry mechanism for failed reminder deliveries

## Technologies Used

- **Backend**: Laravel 12+ (PHP 8.2+)
- **Authentication**: Laravel Sanctum
- **Database**: MySQL 8+ / PostgreSQL 13+ / SQLite (development)
- **Queue System**: Redis with Laravel Queues
- **Containerization**: Docker with Laravel Sail
- **Mail Testing**: Mailpit (for local development)
- **Testing**: PHPUnit/Pest

## Quick Start

### Prerequisites

- **For macOS/Linux**: Docker and Docker Compose
- **For Windows**: Docker Desktop with WSL2 enabled
- Git

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd appointment-reminder-system
   ```

2. **Install Dependencies**
   
   **For Windows/WSL or standard Docker:**
   ```bash
   docker run --rm \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       composer:latest \
       composer install --ignore-platform-reqs
   ```
   
   **For macOS/Linux with Sail:**
   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php82-composer:latest \
       composer install --ignore-platform-reqs
   ```

3. **Setup Environment Configuration**
   ```bash
   cp .env.example .env
   ```

4. **Start the Application**
   
   **For Windows/WSL or standard Docker:**
   ```bash
   docker-compose up -d
   ```
   
   **For macOS/Linux with Sail:**
   ```bash
   ./vendor/bin/sail up -d
   ```

5. **Generate Application Key**
   
   **For Windows/WSL or standard Docker:**
   ```bash
   docker-compose exec laravel.test php artisan key:generate
   ```
   
   **For macOS/Linux with Sail:**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

6. **Run Database Migrations**
   
   **For Windows/WSL or standard Docker:**
   ```bash
   docker-compose exec laravel.test php artisan migrate
   ```
   
   **For macOS/Linux with Sail:**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

7. **Start the Queue Worker**
   
   **For Windows/WSL or standard Docker:**
   ```bash
   docker-compose exec laravel.test php artisan queue:work
   ```
   
   **For macOS/Linux with Sail:**
   ```bash
   ./vendor/bin/sail artisan queue:work
   ```

8. **Start the Scheduler (for recurring appointments)**
   
   **For Windows/WSL or standard Docker:**
   ```bash
   docker-compose exec laravel.test php artisan schedule:work
   ```
   
   **For macOS/Linux with Sail:**
   ```bash
   ./vendor/bin/sail artisan schedule:work
   ```

### Access Points

- **Application**: http://localhost
- **Mailpit (Email Testing)**: http://localhost:8025
- **Database**: localhost:3306 (MySQL)
- **Redis**: localhost:6379

## API Documentation

### Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | Register a new user |
| POST | `/api/login` | Login user |
| POST | `/api/logout` | Logout user |
| GET | `/api/user` | Get authenticated user details |

### Client Management

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/clients` | List all clients | ✅ |
| POST | `/api/clients` | Create new client | ✅ |
| GET | `/api/clients/{id}` | Get specific client | ✅ |
| PUT/PATCH | `/api/clients/{id}` | Update client | ✅ |
| DELETE | `/api/clients/{id}` | Delete client | ✅ |

### Appointment Management

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/appointments` | List appointments | ✅ |
| POST | `/api/appointments` | Create appointment | ✅ |
| GET | `/api/appointments/{id}` | Get specific appointment | ✅ |
| PUT/PATCH | `/api/appointments/{id}` | Update appointment | ✅ |
| DELETE | `/api/appointments/{id}` | Delete appointment | ✅ |
| PATCH | `/api/appointments/{id}/status` | Update appointment status | ✅ |

### Reminder Management

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/reminders` | List all reminders | ✅ |
| GET | `/api/appointments/{id}/reminders` | Get appointment reminders | ✅ |

### Analytics

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/analytics` | Get analytics data | ✅ |
| GET | `/api/analytics/reminders` | Get reminder statistics | ✅ |

### Admin Endpoints

| Method | Endpoint | Description | Auth Required | Admin Required |
|--------|----------|-------------|---------------|----------------|
| GET | `/api/admin/appointments` | List all appointments | ✅ | ✅ |
| GET | `/api/admin/appointments/stats` | Appointment statistics | ✅ | ✅ |
| GET | `/api/admin/users` | List all users | ✅ | ✅ |
| GET | `/api/admin/users/stats` | User statistics | ✅ | ✅ |

## Usage Examples

### Creating an Appointment with Custom Reminders

```bash
curl -X POST http://localhost/api/appointments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "title": "Dental Checkup",
    "description": "Regular cleaning and examination",
    "appointment_time": "2024-12-25 10:00:00",
    "timezone": "America/New_York",
    "reminder_offsets": ["1 day", "2 hours", "15 minutes"]
  }'
```

### Creating a Recurring Appointment

```bash
curl -X POST http://localhost/api/appointments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "title": "Weekly Team Meeting",
    "appointment_time": "2024-12-20 14:00:00",
    "timezone": "UTC",
    "is_recurring": true,
    "recurrence_rule": "FREQ=WEEKLY;UNTIL=20250320T000000Z",
    "reminder_offsets": ["1 hour"]
  }'
```

## Testing

### Create Test Data

**For Windows/WSL or standard Docker:**
```bash
# Create a user first, then get the user ID and run:
docker-compose exec laravel.test php artisan app:test-reminder-system --user-id=1
```

**For macOS/Linux with Sail:**
```bash
# Create a user first, then get the user ID and run:
./vendor/bin/sail artisan app:test-reminder-system --user-id=1
```

### Generate Recurring Appointments

**For Windows/WSL or standard Docker:**
```bash
docker-compose exec laravel.test php artisan app:generate-recurring-appointments --days=30
```

**For macOS/Linux with Sail:**
```bash
./vendor/bin/sail artisan app:generate-recurring-appointments --days=30
```

### Process Due Reminders

**For Windows/WSL or standard Docker:**
```bash
docker-compose exec laravel.test php artisan app:process-due-reminders --limit=100
```

**For macOS/Linux with Sail:**
```bash
./vendor/bin/sail artisan app:process-due-reminders --limit=100
```

### Run Tests

**For Windows/WSL or standard Docker:**
```bash
docker-compose exec laravel.test php artisan test
```

**For macOS/Linux with Sail:**
```bash
./vendor/bin/sail artisan test
```

## Configuration

### Environment Variables

Key environment variables to configure:

```env
# Application
APP_NAME="Appointment Reminder System"
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=appointment_reminder_system

# Queue (Redis recommended)
QUEUE_CONNECTION=redis

# Mail (Mailpit for development)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Queue Configuration

The application uses Redis queues for processing reminders asynchronously. Make sure to run the queue worker:

**For Windows/WSL or standard Docker:**
```bash
docker-compose exec laravel.test php artisan queue:work --tries=3 --backoff=60
```

**For macOS/Linux with Sail:**
```bash
./vendor/bin/sail artisan queue:work --tries=3 --backoff=60
```

### Scheduler Configuration

For production, set up a cron job to run the Laravel scheduler:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Architecture

### Database Schema

- **users** - User accounts with timezone support
- **clients** - Client information with contact details
- **appointments** - Appointment data with timezone and recurring support
- **reminder_dispatches** - Simple reminder tracking (legacy)
- **appointment_reminders** - Advanced reminder system with custom offsets
- **personal_access_tokens** - Sanctum authentication tokens

### Queue Jobs

- **SendReminderJob** - Handles sending reminder notifications
- Supports both simple and custom reminder systems
- Includes retry logic and failure handling

### Console Commands

- **GenerateRecurringAppointments** - Creates recurring appointment instances
- **ProcessDueReminders** - Processes due custom reminders
- **TestReminderSystem** - Creates test data for verification

## Troubleshooting

### Common Issues

1. **Queue not processing**
   - **Windows/WSL**: Check Redis: `docker-compose exec redis redis-cli ping`
   - **Sail**: Check Redis: `./vendor/bin/sail redis redis-cli ping`
   - Start queue worker as shown in Configuration section

2. **Emails not sending**
   - Check Mailpit at http://localhost:8025
   - Verify mail configuration in `.env`

3. **Timezone issues**
   - Ensure all appointment times are stored in UTC
   - Verify timezone strings are valid PHP timezones

4. **Database connection issues**
   - **Windows/WSL**: Check containers: `docker-compose ps`
   - **Sail**: Check containers: `./vendor/bin/sail ps`
   - Verify database credentials in `.env`

5. **WSL2 Performance Issues (Windows users)**
   - Ensure your project is located in the WSL2 filesystem (not Windows filesystem)
   - Use `\\wsl$\Ubuntu\home\username\projects\` for better performance

### Logs

**For Windows/WSL or standard Docker:**
```bash
docker-compose exec laravel.test php artisan log:show
```

**For macOS/Linux with Sail:**
```bash
./vendor/bin/sail artisan log:show
```

Check queue failures:

**For Windows/WSL or standard Docker:**
```bash
docker-compose exec laravel.test php artisan queue:failed
```

**For macOS/Linux with Sail:**
```bash
./vendor/bin/sail artisan queue:failed
```

## Windows-Specific Notes

### WSL2 Setup
1. Ensure WSL2 is enabled and updated
2. Install Docker Desktop with WSL2 backend
3. Clone the project inside WSL2 filesystem for optimal performance
4. Use Windows Terminal or WSL2 terminal for commands

### PowerShell Commands (Alternative)
If you prefer PowerShell, you can use:
```powershell
docker-compose exec laravel.test php artisan migrate
docker-compose exec laravel.test php artisan queue:work
```