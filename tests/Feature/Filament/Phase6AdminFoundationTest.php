<?php

namespace Tests\Feature\Filament;

use App\Models\Station;
use App\Models\User;
use App\Support\StationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6AdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_context_uses_default_station(): void
    {
        $stationA = Station::create([
            'code' => 'ST-A',
            'name' => 'Station A',
            'name_ar' => 'المحطة أ',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $stationB = Station::create([
            'code' => 'ST-B',
            'name' => 'Station B',
            'name_ar' => 'المحطة ب',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active' => true]);

        $user->stations()->attach($stationA->id, ['is_default' => true]);
        $user->stations()->attach($stationB->id, ['is_default' => false]);

        $this->actingAs($user);

        $context = app(StationContext::class);

        $this->assertSame($stationA->id, $context->currentId());
        $this->assertSame($stationA->id, $context->current()?->id);

        $context->set($stationB->id);
        $this->assertSame($stationB->id, $context->currentId());

        $context->set($stationA->id);
        $this->assertSame($stationA->id, $context->currentId());
    }

    public function test_station_context_cannot_select_unassigned_station(): void
    {
        $assigned = Station::create([
            'code' => 'ST-A',
            'name' => 'Station A',
            'name_ar' => 'المحطة أ',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $foreign = Station::create([
            'code' => 'ST-B',
            'name' => 'Station B',
            'name_ar' => 'المحطة ب',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $user->stations()->attach($assigned->id, ['is_default' => true]);

        $this->actingAs($user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(StationContext::class)->set($foreign->id);
    }

    public function test_inactive_or_unassigned_user_cannot_access_filament_panel(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($inactive)
            ->get('/admin')
            ->assertForbidden();

        $activeWithoutStation = User::factory()->create(['is_active' => true]);

        $this->actingAs($activeWithoutStation)
            ->get('/admin')
            ->assertForbidden();
    }
}
