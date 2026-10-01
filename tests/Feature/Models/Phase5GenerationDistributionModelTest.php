<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use App\Models\CustomerConnection;
use App\Models\Feeder;
use App\Models\FeederReading;
use App\Models\GenerationReading;
use App\Models\Generator;
use App\Models\GeneratorRuntimeLog;
use App\Models\Meter;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5GenerationDistributionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_and_distribution_models_are_station_scoped_and_related(): void
    {
        $stationA = Station::create([
            'code' => 'GDA',
            'name' => 'Generation Station A',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $stationB = Station::create([
            'code' => 'GDB',
            'name' => 'Generation Station B',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $generatorA = Generator::create([
            'station_id' => $stationA->id,
            'code' => 'GEN-001',
            'name' => 'Generator 1',
            'capacity_kw' => '500.0000',
            'status' => 'active',
        ]);

        $generatorB = Generator::create([
            'station_id' => $stationB->id,
            'code' => 'GEN-001',
            'name' => 'Generator 1',
            'capacity_kw' => '500.0000',
            'status' => 'active',
        ]);

        $feeder = Feeder::create([
            'station_id' => $stationA->id,
            'code' => 'FDR-001',
            'name' => 'Main Feeder',
            'capacity_kw' => '350.0000',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'station_id' => $stationA->id,
            'code' => 'CUS-GD-001',
            'name' => 'Distribution Customer',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);

        $meter = Meter::create([
            'station_id' => $stationA->id,
            'customer_id' => $customer->id,
            'serial_number' => 'MTR-GD-001',
            'meter_number' => 'M-001',
            'meter_type' => 'electric',
            'phase' => 'three',
            'multiplier' => '1.0000',
            'initial_reading' => '0.0000',
            'installed_at' => '2026-09-01',
            'status' => 'active',
        ]);

        $runtime = GeneratorRuntimeLog::create([
            'station_id' => $stationA->id,
            'generator_id' => $generatorA->id,
            'started_at' => '2026-09-29 08:00:00',
            'stopped_at' => '2026-09-29 12:00:00',
            'hours' => '4.0000',
            'load_percent' => '80.0000',
            'energy_kwh' => '1600.0000',
        ]);

        $generation = GenerationReading::create([
            'transaction_uuid' => '44444444-4444-4444-8444-444444444444',
            'station_id' => $stationA->id,
            'generator_id' => $generatorA->id,
            'reading_at' => '2026-09-29 12:00:00',
            'energy_kwh' => '1600.0000',
            'active_power_kw' => '400.0000',
            'reactive_power_kvar' => '50.0000',
            'source' => 'manual',
        ]);

        $feederReading = FeederReading::create([
            'transaction_uuid' => '55555555-5555-4555-8555-555555555555',
            'station_id' => $stationA->id,
            'feeder_id' => $feeder->id,
            'reading_at' => '2026-09-29 12:00:00',
            'energy_kwh' => '1400.0000',
            'current_amp' => '250.0000',
            'voltage' => '400.0000',
        ]);

        $connection = CustomerConnection::create([
            'station_id' => $stationA->id,
            'customer_id' => $customer->id,
            'meter_id' => $meter->id,
            'feeder_id' => $feeder->id,
            'connected_on' => '2026-09-01',
            'connection_status' => 'connected',
            'connection_load_kw' => '15.0000',
        ]);

        $generatorA->load(['station', 'runtimeLogs', 'readings', 'journalLines']);
        $feeder->load(['station', 'readings', 'customerConnections']);
        $generation->load(['station', 'generator']);
        $feederReading->load(['station', 'feeder']);
        $connection->load(['station', 'customer', 'meter', 'feeder']);
        $customer->load(['connections']);

        $this->assertSame([$generatorA->id], Generator::forStation($stationA->id)->pluck('id')->all());
        $this->assertNotContains($generatorB->id, Generator::forStation($stationA->id)->pluck('id')->all());

        $this->assertSame($stationA->id, $generatorA->station->id);
        $this->assertSame($runtime->id, $generatorA->runtimeLogs->first()->id);
        $this->assertSame($generation->id, $generatorA->readings->first()->id);

        $this->assertSame($stationA->id, $feeder->station->id);
        $this->assertSame($feederReading->id, $feeder->readings->first()->id);
        $this->assertSame($connection->id, $feeder->customerConnections->first()->id);

        $this->assertSame($generatorA->id, $generation->generator->id);
        $this->assertSame($feeder->id, $feederReading->feeder->id);

        $this->assertSame($customer->id, $connection->customer->id);
        $this->assertSame($meter->id, $connection->meter->id);
        $this->assertSame($feeder->id, $connection->feeder->id);
        $this->assertSame($connection->id, $customer->connections->first()->id);
    }
}
