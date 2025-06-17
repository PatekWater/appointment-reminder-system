<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->datetime('send_at'); // When to send this reminder
            $table->enum('method', ['email', 'sms'])->default('email');
            $table->enum('status', ['scheduled', 'sent', 'failed'])->default('scheduled');
            $table->datetime('sent_at')->nullable();
            $table->string('offset_value'); // e.g., '1 hour', '1 day', '30 minutes'
            $table->timestamps();
            
            $table->index(['send_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
