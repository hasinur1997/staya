<?php

namespace App\Actions\Settings;

use App\Enums\AuditLog;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateMessSettings
{
    /**
     * Save the mess settings and log what changed.
     *
     * @param  array{mess_name: string|null, mess_address: string|null, opening_balance: string, fund_start_date: string|null, grocery_edit_window_minutes: int, members_can_view_balance: bool, max_upload_kb: int}  $values
     */
    public function handle(User $actor, array $values): MessSetting
    {
        return DB::transaction(function () use ($actor, $values): MessSetting {
            MessSetting::current();

            /** @var MessSetting $settings */
            $settings = MessSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            $before = $settings->auditSnapshot();

            $settings->fill($values);

            if (! $settings->isDirty()) {
                return $settings;
            }

            $settings->updated_by = $actor->id;
            $settings->save();

            $after = $settings->auditSnapshot();
            $changed = array_keys(array_filter($after, fn (mixed $value, string $key): bool => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));

            activity()
                ->useLog(AuditLog::Settings)
                ->performedOn($settings)
                ->event('updated')
                ->withChanges([
                    'old' => array_intersect_key($before, array_flip($changed)),
                    'attributes' => array_intersect_key($after, array_flip($changed)),
                ])
                ->log('settings_updated');

            return $settings;
        });
    }
}
