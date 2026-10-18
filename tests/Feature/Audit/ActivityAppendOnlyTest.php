<?php

namespace Tests\Feature\Audit;

use App\Exceptions\AppendOnlyViolation;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_rows_cannot_be_updated()
    {
        $activity = activity()->log('something_happened');

        $this->expectException(AppendOnlyViolation::class);

        $activity->update(['description' => 'something_else']);
    }

    public function test_activity_rows_cannot_be_deleted()
    {
        $activity = activity()->log('something_happened');

        try {
            $activity->delete();
            $this->fail('Deleting an activity row should throw.');
        } catch (AppendOnlyViolation) {
            $this->assertModelExists($activity);
        }
    }

    public function test_activity_uses_morph_aliases_and_records_the_ip()
    {
        $user = User::factory()->create();

        $activity = activity()->performedOn($user)->causedBy($user)->log('something_happened');

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertSame('user', $activity->subject_type);
        $this->assertSame('user', $activity->causer_type);
        $this->assertSame('127.0.0.1', $activity->fresh()->ip_address);
    }

    public function test_the_factory_creates_an_activity_with_before_and_after_values()
    {
        $activity = Activity::factory()->withChanges(['name' => 'Old'], ['name' => 'New'])->create();

        $this->assertSame('user', $activity->causer_type);
        $this->assertInstanceOf(User::class, $activity->causer);
        $this->assertSame(['old' => ['name' => 'Old'], 'attributes' => ['name' => 'New']], $activity->fresh()->attribute_changes->all());
    }
}
