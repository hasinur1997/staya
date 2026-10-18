<?php

namespace Tests\Concerns;

use Database\Seeders\AccessControlSeeder;

/**
 * Seeds the permission catalog and system roles before each test. Use after
 * RefreshDatabase. (`$seed` only applies to the first migration of a run.)
 */
trait SeedsAccessControl
{
    protected function setUpSeedsAccessControl(): void
    {
        $this->seed(AccessControlSeeder::class);
    }
}
