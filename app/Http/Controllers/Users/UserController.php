<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show the user list.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::query()
            ->with(['roles', 'roles.permissions', 'permissions'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'roles' => $user->roles->pluck('name')->sort()->values(),
                'can' => [
                    'update' => $actor->can('update', $user),
                ],
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'can' => [
                'create' => $actor->can('create', User::class),
            ],
        ]);
    }

    /**
     * Show the form for a new user.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/create', [
            'roles' => $this->roleOptions($request->user()),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Store a new user.
     */
    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $user = $createUser->handle(
            $request->string('name')->toString(),
            $request->string('name_bn')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->roleIds(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.edit', $user);
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        $actor = $request->user();

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'name_bn' => $user->name_bn,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'role_ids' => $user->roles()->pluck('id'),
            ],
            'roles' => $this->roleOptions($actor),
            'can' => [
                'assignRoles' => ! $actor->is($user),
                'deactivate' => $actor->can('deactivate', $user),
                'reactivate' => $actor->can('reactivate', $user),
            ],
        ]);
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle(
            $request->user(),
            $user,
            $request->string('name')->toString(),
            $request->string('name_bn')->toString(),
            $request->string('email')->toString(),
            $request->roleIds(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.edit', $user);
    }

    /**
     * Deactivate a user.
     */
    public function deactivate(Request $request, User $user, DeactivateUser $deactivateUser): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $deactivateUser->handle($request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deactivated.')]);

        return to_route('users.edit', $user);
    }

    /**
     * Reactivate a user.
     */
    public function reactivate(User $user, ReactivateUser $reactivateUser): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $reactivateUser->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User reactivated.')]);

        return to_route('users.edit', $user);
    }

    /**
     * Every role, marking the ones the actor may assign or remove.
     *
     * @return array<int, array{id: int, name: string, is_system: bool, assignable: bool}>
     */
    private function roleOptions(User $actor): array
    {
        return Role::query()
            ->with('permissions:id,name')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'assignable' => $actor->holdsAllPermissions($role->permissions->pluck('name')),
            ])
            ->all();
    }
}
