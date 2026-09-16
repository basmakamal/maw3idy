<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Set the moment a reminder is claimed, before it is queued, so a
            // second scheduler run can never send the same reminder twice.
            $table->dateTime('reminder_sent_at')->nullable()->after('cancellation_reason');

            $table->index(['starts_at', 'reminder_sent_at'], 'bookings_reminder_scan_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_reminder_scan_index');
            $table->dropColumn('reminder_sent_at');
        });
    }
};
