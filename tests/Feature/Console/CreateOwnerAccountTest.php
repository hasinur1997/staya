<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOwnerAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
        config()->set('staya.owner', [
            'name' => 'Mess Owner',
            'name_bn' => null,
            'email' => 'owner@example.com',
            'password' => 'a-strong-password',
        ]);
    }

    public function test_it_creates_a_verified_admin_from_the_configured_credentials(): void
    {
        $this->artisan('staya:create-owner')->assertSuccessful();

        $owner = User::where('email', 'owner@example.com')->firstOrFail();

        $this->assertTrue($owner->hasRole(Role::admin()));
        $this->assertNotNull($owner->email_verified_at);
        $this->assertSame('Mess Owner', $owner->name_bn);
        $this->assertTrue(auth()->validate(['email' => 'owner@example.com', 'password' => 'a-strong-password']));
    }

    public function test_it_does_nothing_when_an_admin_already_exists(): void
    {
        User::factory()->admin()->create();

        $this->artisan('staya:create-owner')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_it_skips_when_the_credentials_are_not_set(): void
    {
        config()->set('staya.owner.password', null);

        $this->artisan('staya:create-owner')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_it_fails_on_a_weak_password(): void
    {
        config()->set('staya.owner.password', 'short');

        $this->artisan('staya:create-owner')->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }
}
