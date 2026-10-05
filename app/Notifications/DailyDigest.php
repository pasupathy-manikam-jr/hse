<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One morning email per person listing what needs them: actions due, tickets expiring,
 * permits to close. Built by the hse:reminders command.
 */
class DailyDigest extends Notification
{
    use Queueable;

    /**
     * @param  array<string, list<string>>  $sections  heading => lines
     */
    public function __construct(public array $sections) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('HSE: what needs you today'));

        foreach ($this->sections as $heading => $lines) {
            $mail->line("**{$heading}**");

            foreach ($lines as $line) {
                $mail->line('- '.$line);
            }
        }

        return $mail->action(__('Open HSE'), route('dashboard'));
    }
}
