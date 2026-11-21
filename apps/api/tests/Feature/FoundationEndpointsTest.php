<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class FoundationEndpointsTest extends TestCase
{
    public function test_liveness_and_error_envelope_without_database_access(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()->assertJsonPath('data.status', 'ok')->assertHeader('X-Request-ID');

        $response = $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated')
            ->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('X-Request-ID', $response->json('error.request_id'));

        $this->getJson('/api/v1/unknown')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_non_stateful_login_is_rejected_before_credentials_are_read(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'someone@example.test',
            'password' => 'example-passphrase',
        ])->assertStatus(419)->assertJsonPath('error.code', 'csrf_expired');
    }
}
