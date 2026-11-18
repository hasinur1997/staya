<?php

use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The seeder always re-syncs Admin to the whole catalog, so running it
     * gives existing installs the new mail-settings permission.
     */
    public function up(): void
    {
        (new AccessControlSeeder)->run();
    }

    public function down(): void {}
};
