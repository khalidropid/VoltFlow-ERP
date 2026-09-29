<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Station;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase6ControlsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_seeded_and_roles_can_authorize_users(): void
    {
        $this->seed(AccessControlSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'manager@example.test',
        ]);
        $user->assignRole('station_manager');

        $this->assertTrue($user->hasRole('station_manager'));
        $this->assertTrue($user->can('payroll.post'));
        $this->assertFalse($user->can('access.manage'));
        $this->assertDatabaseHas('permissions', ['name' => 'payroll.pay', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'accountant', 'guard_name' => 'web']);
        $this->assertTrue(Permission::where('name', 'reports.view')->exists());
        $this->assertTrue(Role::where('name', 'storekeeper')->exists());
    }

    public function test_reports_are_station_scoped_and_decimal_safe(): void
    {
        $stationA = Station::create([
            'code' => 'RPA',
            'name' => 'Report Station A',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $stationB = Station::create([
            'code' => 'RPB',
            'name' => 'Report Station B',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $customerA = Customer::create([
            'station_id' => $stationA->id,
            'code' => 'RPC-001',
            'name' => 'Report Customer A',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);

        $customerB = Customer::create([
            'station_id' => $stationB->id,
            'code' => 'RPC-001',
            'name' => 'Report Customer B',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);

        $unit = UnitOfMeasure::create([
            'code' => 'PCS',
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        Invoice::create([
            'transaction_uuid' => '10101010-1010-4101-8101-101010101010',
            'station_id' => $stationA->id,
            'customer_id' => $customerA->id,
            'number' => 'RPA-INV-001',
            'invoice_date' => '2026-09-29',
            'total' => '1200.1250',
            'paid_amount' => '200.1250',
            'status' => 'issued',
        ]);

        Invoice::create([
            'transaction_uuid' => '20202020-2020-4202-8202-202020202020',
            'station_id' => $stationB->id,
            'customer_id' => $customerB->id,
            'number' => 'RPB-INV-001',
            'invoice_date' => '2026-09-29',
            'total' => '9000.0000',
            'paid_amount' => '9000.0000',
            'status' => 'paid',
        ]);

        $employee = Employee::create([
            'station_id' => $stationA->id,
            'employee_no' => 'RPE-001',
            'name' => 'Report Employee',
            'status' => 'active',
            'joined_on' => '2026-01-01',
        ]);

        EmployeeContract::create([
            'employee_id' => $employee->id,
            'starts_on' => '2026-01-01',
            'base_salary' => '50000.0000',
            'status' => 'active',
        ]);

        $item = Item::create([
            'station_id' => $stationA->id,
            'code' => 'REPORT-ITEM',
            'name' => 'Report Item',
            'unit_of_measure_id' => $unit->id,
            'item_type' => 'stock',
            'standard_cost' => '10.0000',
            'reorder_level' => '2.0000',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'station_id' => $stationA->id,
            'code' => 'RWH-001',
            'name' => 'Report Warehouse',
            'type' => 'main',
            'is_active' => true,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => '25.5000',
            'average_cost' => '10.1250',
        ]);

        $service = app(\App\Services\Reports\StationReportService::class);
        $reportA = $service->operationalSnapshot($stationA->id, '2026-09-01', '2026-09-30');
        $reportB = $service->operationalSnapshot($stationB->id, '2026-09-01', '2026-09-30');

        $this->assertSame(1, $reportA['billing']['invoice_count']);
        $this->assertSame('1200.1250', $reportA['billing']['billed_total']);
        $this->assertSame('200.1250', $reportA['billing']['paid_total']);
        $this->assertSame('1000.0000', $reportA['billing']['outstanding_total']);
        $this->assertSame('0.0000', $reportA['generation']['energy_kwh']);
        $this->assertSame('0.0000', $reportA['fuel']['issued_cost']);
        $this->assertSame('258.1875', $reportA['inventory']['stock_value']);

        $this->assertSame(1, $reportB['billing']['invoice_count']);
        $this->assertSame('9000.0000', $reportB['billing']['billed_total']);
        $this->assertSame('9000.0000', $reportB['billing']['paid_total']);
        $this->assertSame('0.0000', $reportB['billing']['outstanding_total']);
    }

    public function test_report_rejects_an_invalid_date_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Report start date cannot be after end date.');

        app(\App\Services\Reports\StationReportService::class)
            ->operationalSnapshot(1, '2026-09-30', '2026-09-01');
    }
}
