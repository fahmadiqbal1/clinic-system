<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Override cached config environment: php artisan optimize caches
        // APP_ENV=local from .env, but tests require APP_ENV=testing for
        // CSRF bypass, proper exception handling, etc.
        $this->app['env'] = 'testing';
        config(['app.env' => 'testing']);

        // Ensure roles exist for every test
        $this->seed(RoleSeeder::class);

        // Prevent Vite manifest not found errors in tests
        $this->withoutVite();
    }
}
