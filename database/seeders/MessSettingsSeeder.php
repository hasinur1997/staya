<?php

namespace Database\Seeders;

use App\Models\MessSetting;
use Illuminate\Database\Seeder;

class MessSettingsSeeder extends Seeder
{
    /**
     * Create the settings row with its defaults; an existing row is kept.
     */
    public function run(): void
    {
        MessSetting::current();
    }
}
