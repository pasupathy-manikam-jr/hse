<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Something a person should act on: kept in their notification list (GET /api/v1/notifications,
 * which the app reads when it opens; there is no Firebase push) and sent live over the Reverb hub
 * to whatever is open: event `notification` on `private-App.Models.User.{id}`, the same shape as
 * one item of that list. Sent straight away, so it needs no queue worker.
 */
class HseAlert extends Notification
{
    use Queueable;

    /**
     * @param  array{type: string, id: int}  $target  the record, so the app can open its screen
     */
    public function __construct(public string $title, public string $body, public array $target, public string $url) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([...$this->toArray($notifiable), 'unread' => true, 'created_at' => now()->toIso8601String()]);
    }

    /** Event name the clients bind to. */
    public function broadcastAs(): string
    {
        return 'notification';
    }

    /**
     * @return array{title: string, body: string, target: array{type: string, id: int}, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'target' => $this->target, 'url' => $this->url];
    }
}
