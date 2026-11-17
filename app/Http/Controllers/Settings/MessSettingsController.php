<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateMessSettings;
use App\Concerns\BusinessCalendar;
use App\Enums\CostSplit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMessSettingsRequest;
use App\Models\LedgerEntry;
use App\Models\MessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessSettingsController extends Controller
{
    /**
     * Show the mess settings form.
     */
    public function edit(Request $request): Response
    {
        $settings = MessSetting::current();

        Gate::authorize('view', $settings);

        return Inertia::render('mess-settings/edit', [
            'settings' => [
                'mess_name' => $settings->mess_name,
                'mess_address' => $settings->mess_address,
                'currency' => $settings->currency,
                'timezone' => $settings->timezone,
                'opening_balance' => $settings->opening_balance,
                'fund_start_date' => $settings->fund_start_date?->toDateString(),
                'grocery_edit_window_minutes' => $settings->grocery_edit_window_minutes,
                'members_can_view_balance' => $settings->members_can_view_balance,
                'max_upload_kb' => $settings->max_upload_kb,
                'cost_split' => $settings->cost_split->value,
                'updated_at' => $settings->updated_at?->toIso8601String(),
                'updated_by' => $settings->updatedBy?->name,
            ],
            'costSplits' => CostSplit::options(),
            'today' => BusinessCalendar::today()->toDateString(),
            'can' => [
                'update' => $request->user()->can('update', $settings),
                // Locked once the ledger holds more than the opening balance.
                'changeOpeningBalance' => ! LedgerEntry::hasActivityBeyondOpeningBalance(),
            ],
        ]);
    }

    /**
     * Save the mess settings.
     */
    public function update(UpdateMessSettingsRequest $request, UpdateMessSettings $updateMessSettings): RedirectResponse
    {
        $updateMessSettings->handle($request->user(), $request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('mess-settings.edit');
    }
}
