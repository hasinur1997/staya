<?php

namespace App\Actions\Settings;

use App\Enums\AuditLog;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateMailSettings
{
    /**
     * Save the email settings, switch the running app over to them and log
     * what changed. The password is logged only as "saved" or "not set".
     *
     * @param  array{mailer: string, host: string|null, port: int, encryption: string, username: string|null, from_address: string|null, from_name: string|null, password?: string}  $values
     */
    public function handle(User $actor, array $values): MailSetting
    {
        $settings = DB::transaction(function () use ($actor, $values): MailSetting {
            MailSetting::current();

            /** @var MailSetting $settings */
            $settings = MailSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            $before = $settings->auditSnapshot();
            $passwordChanged = isset($values['password']);

            $settings->fill($values);

            if (! $settings->isDirty()) {
                return $settings;
            }

            $settings->updated_by = $actor->id;
            $settings->save();

            $after = $settings->auditSnapshot();
            $changed = array_keys(array_filter($after, fn (mixed $value, string $key): bool => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));

            if ($passwordChanged && ! in_array('password', $changed, true)) {
                $changed[] = 'password';
                $before['password'] = 'saved';
                $after['password'] = 'changed';
            }

            activity()
                ->useLog(AuditLog::Settings)
                ->performedOn($settings)
                ->event('updated')
                ->withChanges([
                    'old' => array_intersect_key($before, array_flip($changed)),
                    'attributes' => array_intersect_key($after, array_flip($changed)),
                ])
                ->log('mail_settings_updated');

            return $settings;
        });

        MailSetting::applyToConfig();

        return $settings;
    }
}
