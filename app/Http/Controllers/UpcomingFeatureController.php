<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\UpcomingFeature;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UpcomingFeatureController extends Controller
{
    /**
     * Show the "coming soon" page for a sidebar screen not built yet, to
     * the same people the sidebar shows it to.
     */
    public function __invoke(UpcomingFeature $feature): Response
    {
        abort_unless(Gate::any(array_map(
            fn (Permission $permission) => $permission->value,
            $feature->permissions(),
        )), 403);

        return Inertia::render('upcoming', [
            'feature' => [
                'path' => $feature->value,
                'title' => $feature->title(),
                'description' => $feature->description(),
                'highlights' => $feature->highlights(),
            ],
        ]);
    }
}
