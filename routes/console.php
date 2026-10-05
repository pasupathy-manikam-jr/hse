<?php

use Illuminate\Support\Facades\Schedule;

// One digest per person: actions due, competencies expiring, permits to close.
Schedule::command('hse:reminders')->dailyAt('07:00');
