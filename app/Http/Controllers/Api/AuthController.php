<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Sign-in for the app (one Sanctum token per device), the signed-in person, and their notifications.
 */
class AuthController extends Controller
{
    /**
     * Sign in and get a token. Someone with two-factor authentication on must also send `code`
     * (from their authenticator app) or one of their `recovery_code`s.
     *
     * @return array{token: string, user: array<string, mixed>}
     */
    public function login(Request $request): array
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->checkSecondFactor($user, $data['code'] ?? null, $data['recovery_code'] ?? null);
        }

        return [
            'token' => $user->createToken($data['device_name'] ?? 'android')->plainTextToken,
            'user' => $this->profile($user),
        ];
    }

    /** Sign out this device (its token stops working). */
    public function logout(Request $request): Response
    {
        $this->user($request)->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * The signed-in person: role, permissions (to show only what they may do) and home site
     * (null: every site).
     *
     * @return array<string, mixed>
     */
    public function me(Request $request): array
    {
        return $this->profile($this->user($request));
    }

    /**
     * Notifications, newest first. Reading never marks them read; send `since` (ISO time) for
     * only newer ones when the app reopens. Live ones arrive over the Reverb hub while it is open.
     *
     * @return array{data: list<array<string, mixed>>, unread_count: int, next_page: int|null}
     */
    public function notifications(Request $request): array
    {
        $user = $this->user($request);
        $data = $request->validate(['since' => ['nullable', 'date']]);
        $page = $user->notifications()
            ->when($data['since'] ?? null, fn ($q, $since) => $q->where('created_at', '>', Carbon::parse($since)))
            ->paginate(30);

        return [
            'data' => array_values($page->getCollection()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                ...$n->data,
                'unread' => $n->read_at === null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all()),
            'unread_count' => $user->unreadNotifications()->count(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ];
    }

    /** Mark the given notifications read, or all of them when no ids are sent. */
    public function markNotificationsRead(Request $request): Response
    {
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:100'], 'ids.*' => ['string']]);
        $this->user($request)->unreadNotifications()
            ->when($data['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function checkSecondFactor(User $user, ?string $code, ?string $recoveryCode): void
    {
        if ($recoveryCode !== null) {
            if (! in_array($recoveryCode, $user->recoveryCodes(), true)) {
                throw ValidationException::withMessages(['recovery_code' => __('The provided two factor recovery code was invalid.')]);
            }

            $user->replaceRecoveryCode($recoveryCode);

            return;
        }

        if ($code === null) {
            throw ValidationException::withMessages(['code' => __('Enter the code from your authenticator app.')]);
        }

        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

        if (! app(TwoFactorAuthenticationProvider::class)->verify($secret, $code)) {
            throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $user->loadMissing('site:id,code,name', 'contractor:id,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'site' => $user->site,
            'contractor' => $user->contractor,
        ];
    }
}
