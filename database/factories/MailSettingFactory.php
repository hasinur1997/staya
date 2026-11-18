<?php

namespace Database\Factories;

use App\Models\MailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * There is only ever one row; prefer `MailSetting::current()`.
 *
 * @extends Factory<MailSetting>
 */
class MailSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'enabled' => true,
            'host' => 'mail.example.com',
            'port' => 465,
            'encryption' => MailSetting::SSL,
            'username' => 'no-reply@example.com',
            'password' => 'secret-password',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'Staya',
        ];
    }
}
