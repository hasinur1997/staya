<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mess_settings', function (Blueprint $table) {
            // The language shown to visitors who have not chosen one.
            $table->string('default_locale', 5)->default('en');
        });
    }

    public function down(): void
    {
        Schema::table('mess_settings', fn (Blueprint $table) => $table->dropColumn('default_locale'));
    }
};
