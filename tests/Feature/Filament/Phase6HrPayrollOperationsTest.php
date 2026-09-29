<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\PayrollPeriodResource;
use App\Filament\Resources\PayrollRunResource;
use App\Filament\Resources\PayrollSlipResource;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\Station;
use App\Models\User;
use App\Support\StationContext;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6HrPayrollOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_and_payroll_resources_are_authorized_station_scoped_and_posted_slips_are_immutable(): void
    {
        $this->seed(AccessControlSeeder::class);

        $stationA = Station::create([
            'code'=>'HR-A','name'=>'HR A','name_ar'=>'HR A','timezone'=>'Asia/Aden','currency_code'=>'YER','is_active'=>true,
        ]);
        $stationB = Station::create([
            'code'=>'HR-B','name'=>'HR B','name_ar'=>'HR B','timezone'=>'Asia/Aden','currency_code'=>'YER','is_active'=>true,
        ]);

        $employeeA = Employee::create([
            'station_id'=>$stationA->id,'employee_no'=>'EMP-A','name'=>'Employee A','status'=>'active','joined_on'=>'2026-01-01',
        ]);
        $employeeB = Employee::create([
            'station_id'=>$stationB->id,'employee_no'=>'EMP-B','name'=>'Employee B','status'=>'active','joined_on'=>'2026-01-01',
        ]);

        $periodA = PayrollPeriod::create([
            'station_id'=>$stationA->id,'code'=>'2026-09-A','starts_on'=>'2026-09-01','ends_on'=>'2026-09-30','status'=>'open',
        ]);
        $periodB = PayrollPeriod::create([
            'station_id'=>$stationB->id,'code'=>'2026-09-B','starts_on'=>'2026-09-01','ends_on'=>'2026-09-30','status'=>'open',
        ]);

        $runA = PayrollRun::create([
            'station_id'=>$stationA->id,'payroll_period_id'=>$periodA->id,'run_at'=>'2026-09-30 17:00:00','status'=>'draft',
        ]);
        $runB = PayrollRun::create([
            'station_id'=>$stationB->id,'payroll_period_id'=>$periodB->id,'run_at'=>'2026-09-30 17:00:00','status'=>'draft',
        ]);

        $slipA = PayrollSlip::create([
            'payroll_run_id'=>$runA->id,'employee_id'=>$employeeA->id,'slip_number'=>'PS-A',
            'gross_amount'=>'100000.0000','deduction_amount'=>'0.0000','net_amount'=>'100000.0000','status'=>'draft',
        ]);
        $slipB = PayrollSlip::create([
            'payroll_run_id'=>$runB->id,'employee_id'=>$employeeB->id,'slip_number'=>'PS-B',
            'gross_amount'=>'100000.0000','deduction_amount'=>'0.0000','net_amount'=>'100000.0000','status'=>'approved',
        ]);

        $user=User::factory()->create(['is_active'=>true]);
        $user->assignRole('hr_manager');
        $user->stations()->attach($stationA->id,['is_default'=>true]);
        $user->stations()->attach($stationB->id,['is_default'=>false]);
        $this->actingAs($user);
        app(StationContext::class)->set($stationA->id);

        $this->assertTrue(EmployeeResource::canViewAny());
        $this->assertTrue(EmployeeResource::canCreate());
        $this->assertTrue(EmployeeResource::canEdit($employeeA));
        $this->assertTrue(PayrollPeriodResource::canViewAny());
        $this->assertTrue(PayrollPeriodResource::canCreate());
        $this->assertTrue(PayrollRunResource::canViewAny());
        $this->assertTrue(PayrollRunResource::canCreate());
        $this->assertTrue(PayrollRunResource::canEdit($runA));
        $this->assertTrue(PayrollSlipResource::canViewAny());
        $this->assertFalse(PayrollSlipResource::canEdit($slipA));
        $this->assertSame([$employeeA->id], EmployeeResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$periodA->id], PayrollPeriodResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$runA->id], PayrollRunResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$slipA->id], PayrollSlipResource::getEloquentQuery()->pluck('id')->all());

        $slipA->update(['status'=>'paid']);
        $this->assertFalse(PayrollSlipResource::canEdit($slipA->fresh()));

        app(StationContext::class)->set($stationB->id);
        $this->assertSame([$employeeB->id], EmployeeResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$periodB->id], PayrollPeriodResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$runB->id], PayrollRunResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$slipB->id], PayrollSlipResource::getEloquentQuery()->pluck('id')->all());
    }
}
