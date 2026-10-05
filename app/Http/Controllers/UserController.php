<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Contractor;
use App\Models\Site;
use App\Models\User;
use App\Support\TableQuery;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function index(Request $request): Response
    {
        $query = User::query()->with('roles:id,name', 'site:id,code,name', 'contractor:id,name')
            ->when($request->filled('role'), fn (Builder $q) => $q->role($request->string('role')->toString()))
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));

        return Inertia::render('users/index', [
            'users' => TableQuery::paginate($query, $request, ['name', 'email'], ['name', 'email', 'created_at']),
            'roles' => collect(Role::query()->orderBy('id')->pluck('name'))->map(fn (string $name) => ['value' => $name, 'label' => RolesSeeder::label($name)]),
            'sites' => Site::query()->orderBy('name')->get(['id', 'code', 'name']),
            'contractors' => Contractor::query()->orderBy('name')->get(['id', 'name']),
            'filters' => TableQuery::filters($request, ['role', 'site_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([...$this->rules(), ...$this->profileRules(), 'password' => $this->passwordRules()]);

        $user = User::create($data);
        // Created by an admin, so the address is trusted.
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$data['role']]);

        return $this->done(__('User created.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([...$this->rules(), ...$this->profileRules($user->id)]);

        if ($data['role'] !== 'admin' && $this->isLastAdmin($user)) {
            throw ValidationException::withMessages(['role' => __('At least one user must keep the Admin role.')]);
        }

        $user->update($data);
        $user->syncRoles([$data['role']]);

        return $this->done(__('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return $this->toast('error', __('You cannot delete your own account here.'));
        }

        if ($this->isLastAdmin($user)) {
            return $this->toast('error', __('At least one user must keep the Admin role.'));
        }

        $user->delete();

        return $this->done(__('User deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'role' => ['required', Rule::exists('roles', 'name')],
            'site_id' => ['nullable', Rule::exists('sites', 'id')],
            'contractor_id' => ['nullable', Rule::exists('contractors', 'id')],
        ];
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->hasRole('admin') && User::role('admin')->count() === 1;
    }
}
