<?php

use Illuminate\Support\Facades\Schedule;

// One digest per person: actions due, competencies expiring, permits to close.
Schedule::command('hse:reminders')->dailyAt('07:00');

// Last week's summary to each site's report recipients.
Schedule::command('hse:weekly-report')->weeklyOn(1, '07:30');
