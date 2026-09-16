<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Weekly working hours. Times are local to the tenant's timezone; the
        // availability engine resolves them to UTC instants per calendar day.
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO-8601: 1 = Monday … 7 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['staff_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
