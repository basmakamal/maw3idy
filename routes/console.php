<?php

use App\Console\Commands\SendBookingRemindersCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Reminders are found by query, not by delayed jobs, so this runs often
| enough to be timely and is safe to run twice (see the command).
|
*/

Schedule::command(SendBookingRemindersCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
