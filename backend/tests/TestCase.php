<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Spatie's permission/role lookups are cached across requests for
        // performance. RefreshDatabase truncates and re-seeds roles/permissions
        // with fresh IDs between tests, so without this the cache from a
        // previous test can point at stale IDs — causing intermittent,
        // hard-to-diagnose $user->can(...) failures that have nothing to do
        // with the test itself.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
