<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            // Short human-readable code shown to the customer, unique per tenant.
            $table->string('reference', 12);

            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);
            $table->string('customer_email')->nullable();

            // Instants in UTC (DATETIME: no 2038 ceiling, MariaDB-safe). Display converts to the tenant's timezone.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Snapshot of the service at booking time; later edits to the service must not rewrite history.
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_after_minutes');
            $table->decimal('price', 10, 2);

            $table->string('status', 20)->default('confirmed');

            // TRUE while the booking holds its slot, NULL once cancelled. MySQL ignores NULLs in
            // unique indexes, so this turns (staff_id, starts_at) into "unique among confirmed".
            $table->boolean('slot_lock')->nullable()->default(true);

            // Capability token for the customer's cancel/reschedule link (Phase 3).
            $table->string('cancel_token', 64)->unique();

            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->unique(['staff_id', 'starts_at', 'slot_lock'], 'bookings_staff_slot_unique');
            $table->index(['staff_id', 'starts_at', 'ends_at']);
            $table->index(['tenant_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
