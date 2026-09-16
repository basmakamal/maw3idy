<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Absences that override the weekly schedule: holidays, sick days, a long lunch.
        Schema::create('time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            // DATETIME, not TIMESTAMP: no 2038 ceiling, and MariaDB refuses a second
            // NOT NULL TIMESTAMP without a default. Values are always UTC.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off');
    }
};
