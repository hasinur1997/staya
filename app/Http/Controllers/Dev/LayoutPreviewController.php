<?php

namespace App\Http\Controllers\Dev;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Local-only preview of the app shell and the five page templates. The
 * `as` query fakes a default role's permission set so the navigation can be
 * checked for each one. It only changes what the page displays; it grants
 * nothing.
 */
class LayoutPreviewController extends Controller
{
    private const TEMPLATES = ['dashboard', 'list', 'detail', 'form', 'report'];

    /**
     * Show the layout preview.
     */
    public function __invoke(Request $request): Response
    {
        abort_unless(app()->isLocal(), 404);

        $roles = array_map(fn (SystemRole $role) => strtolower($role->value), SystemRole::cases());
        $as = $request->query('as');
        $as = is_string($as) && in_array($as, $roles, true) ? $as : 'admin';
        $template = $request->query('template');
        $template = in_array($template, self::TEMPLATES, true) ? $template : 'dashboard';
        $permissions = array_map(
            fn (Permission $permission) => $permission->value,
            SystemRole::from(ucfirst($as))->defaultPermissions(),
        );
        $holds = fn (string ...$keys): bool => array_intersect($keys, $permissions) !== [];

        Inertia::share('auth.permissions', $permissions);
        Inertia::share('nav.counts', [
            'pendingApprovals' => $holds('deposit.approve', 'member.approve_registration') ? 3 : null,
            'openPayables' => $holds('ledger.reimburse') ? 1 : null,
        ]);
        Inertia::share('fund.balance', $holds('ledger.view_balance') ? '12450.00' : null);

        return Inertia::render('layout-preview', [
            'as' => $as,
            'roles' => $roles,
            'template' => $template,
            'templates' => self::TEMPLATES,
        ]);
    }
}
