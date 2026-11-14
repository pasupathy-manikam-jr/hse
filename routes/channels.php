<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Live notifications (HseAlert): each person listens on their own private channel through the
// shared Reverb hub. web = session (browser), sanctum = API token (the Android app).
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === $id, ['guards' => ['web', 'sanctum']]);
