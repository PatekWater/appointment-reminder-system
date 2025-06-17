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
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('status');
            $table->string('recurrence_rule')->nullable()->after('is_recurring'); // RRULE format
            $table->foreignId('parent_appointment_id')->nullable()->constrained('appointments')->onDelete('cascade')->after('recurrence_rule');
            $table->json('reminder_offsets')->nullable()->after('parent_appointment_id'); // ['1 day', '1 hour']
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['parent_appointment_id']);
            $table->dropColumn(['is_recurring', 'recurrence_rule', 'parent_appointment_id', 'reminder_offsets']);
        });
    }
};
