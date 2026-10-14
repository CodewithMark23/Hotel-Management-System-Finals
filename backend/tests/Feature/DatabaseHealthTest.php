<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class DatabaseHealthTest extends TestCase
{
    public function test_production_without_an_external_database_has_a_clear_error(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['database.default' => 'sqlite']);

        $this->getJson('/api/health/database')->assertStatus(503)
            ->assertJsonPath('code', 'DATABASE_NOT_CONFIGURED');
    }

    public function test_connection_error_never_exposes_credentials(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new PDOException('Connection failed: postgres://admin:private-password@private-host/database'));

        $response = $this->getJson('/api/health/database')->assertStatus(503)
            ->assertJsonPath('code', 'DATABASE_CONNECTION_FAILED');
        $this->assertStringNotContainsString('private-password', $response->getContent());
        $this->assertStringNotContainsString('private-host', $response->getContent());
    }

    public function test_invalid_database_password_has_a_safe_error_message(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new PDOException('password authentication failed for user private-user'));

        $this->getJson('/api/health/database')->assertStatus(503)
            ->assertJsonPath('code', 'DATABASE_AUTHENTICATION_FAILED');
    }
}
