<?php

namespace App\Http\Middleware;

use App\Enums\DepositStatus;
use App\Enums\MemberStatus;
use App\Models\Deposit;
use App\Models\Fund;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = MessSetting::current();

        return [
            ...parent::share($request),
            'name' => $settings->mess_name ?? config('app.name'),
            'locale' => app()->getLocale(),
            // Sent once per visit; English strings are their own keys.
            'translations' => Inertia::once(fn (): array => $this->translations()),
            'auth' => [
                'user' => $request->user(),
                'permissions' => fn (): array => $this->permissions($request),
            ],
            // Badge counts for the sidebar and the top-bar menu. Each is null
            // unless the user holds the permission it belongs to.
            'nav' => [
                'counts' => [
                    'pendingApprovals' => fn (): ?int => $this->pendingApprovals($request),
                    'openPayables' => null,
                ],
            ],
            // Mess-wide rules the UI mirrors; the server still enforces them.
            'mess' => [
                'editWindowMinutes' => $settings->grocery_edit_window_minutes,
                'maxUploadKb' => $settings->max_upload_kb,
            ],
            // Decimal string, or null when the user may not see the balance.
            'fund' => [
                'balance' => fn (): ?string => $request->user()?->can('viewBalance', Fund::class)
                    ? Fund::current()->balance
                    : null,
            ],
        ];
    }

    /**
     * Deposits and registrations waiting for a decision, counting only the
     * queues the user may review; null when they review neither.
     */
    protected function pendingApprovals(Request $request): ?int
    {
        $user = $request->user();
        $reviewsDeposits = $user?->can('review', Deposit::class) ?? false;
        $reviewsRegistrations = $user?->can('viewAnyRegistrations', Member::class) ?? false;

        if (! $reviewsDeposits && ! $reviewsRegistrations) {
            return null;
        }

        return ($reviewsDeposits ? Deposit::query()->where('status', DepositStatus::Pending)->count() : 0)
            + ($reviewsRegistrations ? Member::query()->where('status', MemberStatus::Pending)->count() : 0);
    }

    /**
     * The signed-in user's effective permission names, used by the frontend
     * to mirror (never enforce) access, e.g. which nav items to show.
     *
     * @return list<string>
     */
    protected function permissions(Request $request): array
    {
        $permissions = $request->user()?->getAllPermissions()->all() ?? [];

        return array_values(array_map(fn (Permission $permission): string => $permission->name, $permissions));
    }

    /**
     * The JSON translations for the current locale (`lang/{locale}.json`).
     *
     * @return array<string, string>
     */
    protected function translations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }
}
