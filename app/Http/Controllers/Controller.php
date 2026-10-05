<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Go back to the previous page with a toast notification.
     */
    protected function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    /**
     * The signed-in user (every app route requires one).
     */
    protected function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    protected function done(string $message): RedirectResponse
    {
        return $this->toast('success', $message);
    }
}
