<?php

namespace App\Console\Commands;

use App\Models\Action;
use App\Models\Equipment;
use App\Models\Permit;
use App\Models\User;
use App\Models\UserCompetency;
use App\Notifications\DailyDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The daily reminder run (scheduled at 07:00): one digest per person with their actions due
 * or overdue, their own competencies expiring within 30 days, permits they requested that are
 * past their end time but still open, and in-service equipment they own whose inspection is due
 * within 30 days or overdue.
 */
#[Signature('hse:reminders')]
#[Description('Email each person a digest of actions due, competencies expiring and permits to close')]
class SendReminders extends Command
{
    public function handle(): int
    {
        /** @var array<int, array<string, list<string>>> $digests */
        $digests = [];

        Action::query()->where('status', 'open')->whereDate('due_on', '<=', today()->addDay())
            ->orderBy('due_on')->get()
            ->each(function (Action $action) use (&$digests) {
                $when = $action->due_on->isPast() && ! $action->due_on->isToday() ? __('overdue since :d', ['d' => $action->due_on->toDateString()]) : __('due :d', ['d' => $action->due_on->toDateString()]);
                $digests[$action->owner_id][__('Actions due')][] = "{$action->number} ({$when}): {$action->description}";
            });

        // Only each person's latest record per competency counts: a renewal replaces the old one.
        UserCompetency::query()->with('competency:id,name')->orderByDesc('issued_on')->orderByDesc('id')->get()
            ->unique(fn (UserCompetency $c) => "{$c->user_id}-{$c->competency_id}")
            ->filter(fn (UserCompetency $c) => $c->expires_on !== null && $c->expires_on->between(today(), today()->addDays(UserCompetency::EXPIRING_DAYS)))
            ->each(function (UserCompetency $c) use (&$digests) {
                $digests[$c->user_id][__('Competencies expiring')][] = __(':name expires :d', ['name' => $c->competency->name, 'd' => $c->expires_on?->toDateString()]);
            });

        Permit::query()->whereIn('status', ['active', 'suspended'])->where('valid_to', '<', now())->whereNotNull('created_by')
            ->get()
            ->each(function (Permit $permit) use (&$digests) {
                $digests[(int) $permit->created_by][__('Permits to close')][] = __(':number ended :t and is still open.', ['number' => $permit->number, 't' => $permit->valid_to->format('Y-m-d H:i')]);
            });

        Equipment::query()->where('status', 'in-service')->whereNotNull('owner_id')
            ->where(fn ($q) => $q->whereNull('next_inspection_on')->orWhereDate('next_inspection_on', '<=', today()->addDays(Equipment::DUE_SOON_DAYS)))
            ->orderBy('next_inspection_on')->get()
            ->each(function (Equipment $item) use (&$digests) {
                $when = $item->next_inspection_on === null ? __('never inspected') : __('due :d', ['d' => $item->next_inspection_on->toDateString()]);
                $digests[(int) $item->owner_id][__('Equipment inspections')][] = "{$item->asset_tag} {$item->name} ({$when})";
            });

        $users = User::query()->whereKey(array_keys($digests))->get();

        foreach ($users as $user) {
            $user->notify(new DailyDigest($digests[$user->id]));
        }

        $this->info(__('Sent :n reminder emails.', ['n' => $users->count()]));

        return self::SUCCESS;
    }
}
