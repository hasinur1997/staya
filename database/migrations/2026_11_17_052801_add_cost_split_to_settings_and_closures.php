<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mess_settings', function (Blueprint $table) {
            // How a month's expenses are divided: `equal` or `meals`.
            $table->string('cost_split', 10)->default('equal');
        });

        Schema::table('month_closures', function (Blueprint $table) {
            // The split in force when the month was closed; the month keeps
            // it for as long as it stays closed.
            $table->string('cost_split', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('month_closures', fn (Blueprint $table) => $table->dropColumn('cost_split'));
        Schema::table('mess_settings', fn (Blueprint $table) => $table->dropColumn('cost_split'));
    }
};
