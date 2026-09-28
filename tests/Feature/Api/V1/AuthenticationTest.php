<?php

namespace Tests\Feature\Api\V1;

use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_station_membership(): void
    {
        $station = Station::create(['code' => 'ST-001', 'name' => 'Main Station']);
        User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => Hash::make('secret'), 'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret',
            'station_id' => $station->id,
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_issues_a_sanctum_token_for_assigned_station(): void
    {
        $station = Station::create(['code' => 'ST-001', 'name' => 'Main Station']);
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => Hash::make('secret'), 'is_active' => true,
        ]);
        $user->stations()->attach($station, ['is_default' => true]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret',
            'station_id' => $station->id,
            'device_name' => 'test-device',
        ]);

        $response->assertOk()->assertJsonPath('data.token_type', 'Bearer');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'test-device']);
    }
}
