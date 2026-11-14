<?php

namespace App\Notifications;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Last week's HSE figures for one site, mailed to its report recipients on Monday morning.
 * Figures only; names and incident details stay behind the sign-in link.
 */
class WeeklySummary extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $report  WeeklyReport::data()
     */
    public function __construct(public Site $site, public array $report) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->report;
        /** @var Collection<string, int> $observations */
        $observations = $r['observations'];
        /** @var Collection<string, int> $topics */
        $topics = $r['topics'];
        $score = $r['prevention']['inspection_score'];
        $days = $r['daysSinceLti'][0]['days'] ?? null;

        $mail = (new MailMessage)
            ->subject(__('HSE week :n: :site', ['n' => $r['week']['number'], 'site' => $this->site->code]))
            ->greeting(__('Week :n, :from to :to', ['n' => $r['week']['number'], 'from' => $r['week']['from'], 'to' => $r['week']['to']]))
            ->line("**{$this->site->code} · {$this->site->name}**")
            ->line(__('Observations: :n', ['n' => $observations->sum()]).($topics->isNotEmpty()
                ? ' ('.__('mostly :topics', ['topics' => $topics->keys()->take(3)->map(fn (string $t) => Str::headline($t))->implode(', ')]).')' : ''))
            ->line(__('Incidents: :n', ['n' => count($r['incidents'])]))
            ->line(__('Inspections: :n', ['n' => $r['prevention']['inspections']]).($score === null ? '' : ' ('.__('average :s%', ['s' => round((float) $score)]).')'))
            ->line(__('Toolbox talks: :n (:a attended)', ['n' => $r['prevention']['toolbox_talks'], 'a' => $r['prevention']['attendees']]))
            ->line(__('Actions: :raised raised, :verified verified, :overdue overdue', ['raised' => $r['actions']['raised'], 'verified' => $r['actions']['verified'], 'overdue' => count($r['actions']['overdue'])]))
            ->line(__('Equipment overdue for inspection: :n', ['n' => count($r['equipmentOverdue'])]))
            ->line($days === null ? __('No lost-time injury recorded.') : __('Days since the last lost-time injury: :n', ['n' => $days]));

        return $mail->action(__('Open the full report'), route('reports.weekly', ['week' => $r['week']['from'], 'site_id' => $this->site->id]));
    }
}
