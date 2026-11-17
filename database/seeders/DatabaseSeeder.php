<?php

namespace Database\Seeders;

use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([AccessControlSeeder::class, MessSettingsSeeder::class]);

        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $members = Member::factory()->active()->count(4)->create();
        Member::factory()->inactive()->create();
        Member::factory()->count(2)->create();

        foreach ($members as $member) {
            Deposit::factory()->approved()->create(['member_id' => $member->id]);
        }

        Deposit::factory()->create(['member_id' => $members->first()?->id]);

        foreach ($members->take(2) as $member) {
            GroceryPurchase::factory()->settled()->create(['buyer_member_id' => $member->id]);
        }
    }
}
