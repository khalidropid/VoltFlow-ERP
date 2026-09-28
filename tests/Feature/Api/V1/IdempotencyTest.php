<?php

namespace Tests\Feature\Api\V1;

use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_logout_request_is_replayed(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $key = 'logout-test-001';
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/auth/logout');
        $second = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/auth/logout');

        $first->assertOk();
        $second->assertOk()->assertJson($first->json());
    }
}
