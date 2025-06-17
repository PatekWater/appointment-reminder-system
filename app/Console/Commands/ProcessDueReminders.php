<?php

namespace App\Console\Commands;

use App\Jobs\SendReminderJob;
use App\Models\AppointmentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDueReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-due-reminders {--limit=100 : Maximum number of reminders to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due appointment reminders and dispatch notification jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("🔄 Processing due appointment reminders (limit: {$limit})...");
        $this->newLine();

        // Get due reminders that haven't been sent yet
        $dueReminders = AppointmentReminder::due()
            ->with(['appointment.client'])
            ->limit($limit)
            ->get();

        if ($dueReminders->isEmpty()) {
            $this->info('📅 No due reminders found.');
            return Command::SUCCESS;
        }

        $processed = 0;
        $errors = 0;

        foreach ($dueReminders as $reminder) {
            try {
                // Validate that the appointment and client still exist
                if (!$reminder->appointment || !$reminder->appointment->client) {
                    $this->warn("⚠️  Skipping reminder ID {$reminder->id}: Missing appointment or client");
                    $reminder->update([
                        'status' => 'failed',
                        'sent_at' => now()
                    ]);
                    $errors++;
                    continue;
                }

                // Dispatch the reminder job immediately (since it's already due)
                SendReminderJob::dispatchSync($reminder->appointment, null, $reminder);
                
                $this->info("✅ Processed reminder for: {$reminder->appointment->title} (offset: {$reminder->offset_value})");
                $processed++;

            } catch (\Exception $e) {
                $this->error("❌ Failed to process reminder ID {$reminder->id}: {$e->getMessage()}");
                
                // Mark as failed
                $reminder->update([
                    'status' => 'failed',
                    'sent_at' => now()
                ]);
                
                Log::error('Failed to process due reminder', [
                    'reminder_id' => $reminder->id,
                    'appointment_id' => $reminder->appointment_id,
                    'error' => $e->getMessage()
                ]);
                
                $errors++;
            }
        }

        $this->newLine();
        $this->info("📊 Processing complete!");
        $this->info("   ✅ Successfully processed: {$processed}");
        
        if ($errors > 0) {
            $this->warn("   ❌ Errors encountered: {$errors}");
        }

        // Log summary
        Log::info('Due reminders processing completed', [
            'processed' => $processed,
            'errors' => $errors,
            'total_found' => $dueReminders->count()
        ]);

        return Command::SUCCESS;
    }
}
