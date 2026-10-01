<?php

namespace Tests\Feature\Models;

use App\Models\AdvanceRepayment;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceLog;
use App\Models\ChartOfAccount;
use App\Models\CashAccount;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAccountLink;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeContract;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRecord;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Models\Position;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5HrPayrollModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_and_payroll_models_are_related_and_station_scoped(): void
    {
        $stationA = Station::create([
            'code' => 'HRA',
            'name' => 'HR Station A',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $stationB = Station::create([
            'code' => 'HRB',
            'name' => 'HR Station B',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $department = Department::create([
            'station_id' => $stationA->id,
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $position = Position::create([
            'station_id' => $stationA->id,
            'code' => 'TECH',
            'name' => 'Technician',
        ]);

        $employee = Employee::create([
            'station_id' => $stationA->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_no' => 'EMP-001',
            'name' => 'Ali Ahmed',
            'phone' => '777000001',
            'status' => 'active',
            'joined_on' => '2026-01-01',
        ]);

        $employeeB = Employee::create([
            'station_id' => $stationB->id,
            'employee_no' => 'EMP-001',
            'name' => 'Hassan Saleh',
            'status' => 'active',
            'joined_on' => '2026-01-01',
        ]);

        $contract = EmployeeContract::create([
            'employee_id' => $employee->id,
            'starts_on' => '2026-01-01',
            'base_salary' => '150000.0000',
            'pay_frequency' => 'monthly',
            'contract_type' => 'permanent',
            'status' => 'active',
        ]);

        $bank = EmployeeBankAccount::create([
            'employee_id' => $employee->id,
            'bank_name' => 'Yemen Bank',
            'account_name' => 'Ali Ahmed',
            'account_number' => '00112233',
            'iban' => 'YE00TEST00112233',
            'is_primary' => true,
        ]);

        $shift = Shift::create([
            'station_id' => $stationA->id,
            'code' => 'DAY',
            'name' => 'Day Shift',
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'break_minutes' => 60,
        ]);

        $assignment = ShiftAssignment::create([
            'station_id' => $stationA->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'starts_on' => '2026-09-01',
        ]);

        $attendance = AttendanceLog::create([
            'station_id' => $stationA->id,
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-29',
            'check_in' => '2026-09-29 08:05:00',
            'check_out' => '2026-09-29 16:10:00',
            'worked_hours' => '7.0833',
            'status' => 'late',
        ]);

        $adjustment = AttendanceAdjustment::create([
            'attendance_log_id' => $attendance->id,
            'hours_delta' => '0.5000',
            'reason' => 'Approved overtime adjustment',
        ]);

        $leaveType = LeaveType::create([
            'station_id' => $stationA->id,
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'is_paid' => true,
            'annual_days' => '30.0000',
        ]);

        $leave = LeaveRequest::create([
            'station_id' => $stationA->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-03',
            'days' => '3.0000',
            'status' => 'approved',
        ]);

        $overtime = OvertimeRecord::create([
            'station_id' => $stationA->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-09-29',
            'hours' => '2.0000',
            'rate_multiplier' => '1.5000',
            'amount' => '2500.0000',
            'status' => 'approved',
        ]);

        $advance = EmployeeAdvance::create([
            'transaction_uuid' => '12121212-1212-4121-8121-121212121212',
            'station_id' => $stationA->id,
            'employee_id' => $employee->id,
            'advance_date' => '2026-09-10',
            'amount' => '10000.0000',
            'balance' => '10000.0000',
            'status' => 'open',
            'reason' => 'Employee advance',
        ]);

        $earning = SalaryComponent::create([
            'station_id' => $stationA->id,
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'type' => 'earning',
            'is_taxable' => false,
        ]);

        $deduction = SalaryComponent::create([
            'station_id' => $stationA->id,
            'code' => 'ADV',
            'name' => 'Advance Repayment',
            'type' => 'deduction',
            'is_taxable' => false,
        ]);

        $employeeComponent = EmployeeSalaryComponent::create([
            'employee_id' => $employee->id,
            'salary_component_id' => $earning->id,
            'amount' => '150000.0000',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $payrollPeriod = PayrollPeriod::create([
            'station_id' => $stationA->id,
            'code' => '2026-09',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'open',
        ]);

        $payrollRun = PayrollRun::create([
            'station_id' => $stationA->id,
            'payroll_period_id' => $payrollPeriod->id,
            'run_at' => '2026-09-30 17:00:00',
            'status' => 'completed',
        ]);

        $slip = PayrollSlip::create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
            'slip_number' => 'PAY-2026-09-0001',
            'gross_amount' => '152500.0000',
            'deduction_amount' => '5000.0000',
            'net_amount' => '147500.0000',
            'status' => 'approved',
        ]);

        $earningLine = PayrollSlipLine::create([
            'payroll_slip_id' => $slip->id,
            'salary_component_id' => $earning->id,
            'description' => 'Basic salary',
            'line_type' => 'earning',
            'amount' => '150000.0000',
        ]);

        $overtimeLine = PayrollSlipLine::create([
            'payroll_slip_id' => $slip->id,
            'description' => 'Approved overtime',
            'line_type' => 'earning',
            'amount' => '2500.0000',
        ]);

        $deductionLine = PayrollSlipLine::create([
            'payroll_slip_id' => $slip->id,
            'salary_component_id' => $deduction->id,
            'description' => 'Advance repayment',
            'line_type' => 'deduction',
            'amount' => '5000.0000',
        ]);

        $repayment = AdvanceRepayment::create([
            'employee_advance_id' => $advance->id,
            'payroll_slip_id' => $slip->id,
            'repayment_date' => '2026-09-30',
            'amount' => '5000.0000',
        ]);

        $expenseAccount = ChartOfAccount::create([
            'station_id' => $stationA->id,
            'code' => '6100',
            'name' => 'Salary Expense',
            'type' => 'expense',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $payableAccount = ChartOfAccount::create([
            'station_id' => $stationA->id,
            'code' => '2100',
            'name' => 'Employee Payables',
            'type' => 'liability',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $employeeAccountLink = EmployeeAccountLink::create([
            'station_id' => $stationA->id,
            'employee_id' => $employee->id,
            'payable_account_id' => $payableAccount->id,
            'salary_expense_account_id' => $expenseAccount->id,
        ]);

        $bankAccount = ChartOfAccount::create([
            'station_id' => $stationA->id,
            'code' => '1110',
            'name' => 'Bank - Payroll',
            'type' => 'asset',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $cashAccount = CashAccount::create([
            'station_id' => $stationA->id,
            'code' => 'BANK-001',
            'name' => 'Payroll Bank',
            'type' => 'bank',
            'account_id' => $bankAccount->id,
            'balance' => '1000000.0000',
            'is_active' => true,
        ]);

        $payrollPayment = PayrollPayment::create([
            'transaction_uuid' => '34343434-3434-4343-8343-343434343434',
            'station_id' => $stationA->id,
            'payroll_slip_id' => $slip->id,
            'cash_account_id' => $cashAccount->id,
            'paid_at' => '2026-09-30 18:00:00',
            'amount' => '147500.0000',
            'method' => 'bank',
            'status' => 'posted',
        ]);

        $department->load(['station', 'employees']);
        $position->load(['station', 'employees']);
        $employee->load([
            'station',
            'department',
            'position',
            'contracts',
            'bankAccounts',
            'shiftAssignments.shift',
            'attendanceLogs.adjustments',
            'leaveRequests.leaveType',
            'overtimeRecords',
            'advances.repayments',
            'salaryComponents.salaryComponent',
            'payrollSlips.lines.salaryComponent',
            'payrollSlips.payrollRun.payrollPeriod',
            'accountLink.payableAccount',
            'accountLink.salaryExpenseAccount',
        ]);
        $shift->load(['station', 'assignments.employee']);
        $attendance->load(['employee', 'station', 'adjustments']);
        $leave->load(['employee', 'leaveType']);
        $overtime->load(['employee', 'station']);
        $advance->load(['employee', 'repayments.payrollSlip']);
        $payrollPeriod->load(['station', 'payrollRuns.slips.employee']);
        $payrollRun->load(['station', 'payrollPeriod', 'slips.employee', 'slips.lines']);
        $slip->load(['payrollRun', 'employee', 'lines.salaryComponent', 'advanceRepayments', 'payment.cashAccount']);
        $payrollPayment->load(['station', 'payrollSlip', 'cashAccount']);
        $employeeAccountLink->load(['station', 'employee', 'payableAccount', 'salaryExpenseAccount']);

        $this->assertSame([$employee->id], Employee::forStation($stationA->id)->pluck('id')->all());
        $this->assertNotContains($employeeB->id, Employee::forStation($stationA->id)->pluck('id')->all());

        $this->assertSame($stationA->id, $employee->station->id);
        $this->assertSame($department->id, $employee->department->id);
        $this->assertSame($position->id, $employee->position->id);
        $this->assertSame($contract->id, $employee->contracts->first()->id);
        $this->assertSame($bank->id, $employee->bankAccounts->first()->id);
        $this->assertSame($assignment->id, $employee->shiftAssignments->first()->id);
        $this->assertSame($attendance->id, $employee->attendanceLogs->first()->id);
        $this->assertSame($leave->id, $employee->leaveRequests->first()->id);
        $this->assertSame($overtime->id, $employee->overtimeRecords->first()->id);
        $this->assertSame($advance->id, $employee->advances->first()->id);
        $this->assertSame($employeeComponent->id, $employee->salaryComponents->first()->id);
        $this->assertSame($employeeAccountLink->id, $employee->accountLink->id);

        $this->assertSame($attendance->id, $adjustment->attendanceLog->id);
        $this->assertSame($employee->id, $shift->assignments->first()->employee->id);
        $this->assertSame($leaveType->id, $leave->leaveType->id);
        $this->assertSame($employee->id, $overtime->employee->id);
        $this->assertSame($repayment->id, $advance->repayments->first()->id);

        $this->assertSame($payrollRun->id, $payrollPeriod->payrollRuns->first()->id);
        $this->assertSame($payrollPeriod->id, $payrollRun->payrollPeriod->id);
        $this->assertSame($slip->id, $payrollRun->slips->first()->id);
        $this->assertSame($employee->id, $slip->employee->id);
        $this->assertSame(3, $slip->lines->count());
        $this->assertSame($earning->id, $earningLine->salaryComponent->id);
        $this->assertSame($deduction->id, $deductionLine->salaryComponent->id);
        $this->assertSame($repayment->id, $slip->advanceRepayments->first()->id);
        $this->assertSame($payrollPayment->id, $slip->payment->id);
        $this->assertSame($cashAccount->id, $payrollPayment->cashAccount->id);

        $this->assertSame($employee->id, $employeeAccountLink->employee->id);
        $this->assertSame($payableAccount->id, $employeeAccountLink->payableAccount->id);
        $this->assertSame($expenseAccount->id, $employeeAccountLink->salaryExpenseAccount->id);
        $this->assertSame($stationA->id, $payrollPayment->station->id);
        $this->assertSame($slip->id, $payrollPayment->payrollSlip->id);
    }
}
