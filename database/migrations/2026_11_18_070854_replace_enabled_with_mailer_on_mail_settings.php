<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            // `default` leaves the server's .env in charge; otherwise the mailer to use.
            $table->string('mailer', 20)->default('default')->after('id');
        });

        DB::table('mail_settings')->where('enabled', true)->update(['mailer' => 'smtp']);

        Schema::table('mail_settings', fn (Blueprint $table) => $table->dropColumn('enabled'));
    }

    public function down(): void
    {
        Schema::table('mail_settings', fn (Blueprint $table) => $table->boolean('enabled')->default(false));

        DB::table('mail_settings')->where('mailer', 'smtp')->update(['enabled' => true]);

        Schema::table('mail_settings', fn (Blueprint $table) => $table->dropColumn('mailer'));
    }
};
