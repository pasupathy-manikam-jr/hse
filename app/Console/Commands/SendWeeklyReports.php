<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Notifications\WeeklySummary;
use App\Support\WeeklyReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Monday morning: last week's summary for each active site, to the addresses on its report list.
 */
#[Signature('hse:weekly-report')]
#[Description('Email last week\'s HSE summary to each site\'s report recipients')]
class SendWeeklyReports extends Command
{
    public function handle(): int
    {
        $lastWeek = CarbonImmutable::today()->subWeek();
        $sent = 0;

        foreach (Site::query()->where('active', true)->whereNotNull('report_recipients')->get() as $site) {
            if ($site->report_recipients === []) {
                continue;
            }

            Notification::route('mail', $site->report_recipients)
                ->notify(new WeeklySummary($site, (new WeeklyReport([$site->id], $lastWeek))->data()));
            $sent++;
        }

        $this->info(trans_choice('Sent the weekly report for :n site.|Sent the weekly report for :n sites.', $sent, ['n' => $sent]));

        return self::SUCCESS;
    }
}
