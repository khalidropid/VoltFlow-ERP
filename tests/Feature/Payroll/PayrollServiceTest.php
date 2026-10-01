<?php

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Support\Decimal;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\EmployeeAccountLink;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeContract;
use App\Models\EmployeeSalaryComponent;
use App\Models\FiscalPeriod;
use App\Models\OvertimeRecord;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\Station;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_recalculate_and_approve_slip_with_advance_repayment(): void
    {
        [$station, $employee, $run, $accounts] = $this->makePayrollContext();

        $earning = SalaryComponent::create([
            'station_id' => $station->id,
            'code' => 'HOUSING',
            'name' => 'Housing Allowance',
            'type' => 'earning',
            'is_taxable' => false,
        ]);

        $deduction = SalaryComponent::create([
            'station_id' => $station->id,
            'code' => 'TAX',
            'name' => 'Tax',
            'type' => 'deduction',
            'is_taxable' => true,
            'account_id' => $accounts['tax']->id,
        ]);

        EmployeeSalaryComponent::create([
            'employee_id' => $employee->id,
            'salary_component_id' => $earning->id,
            'amount' => '10000.0000',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        EmployeeSalaryComponent::create([
            'employee_id' => $employee->id,
            'salary_component_id' => $deduction->id,
            'amount' => '5000.0000',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        OvertimeRecord::create([
            'station_id' => $station->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'hours' => '2.0000',
            'rate_multiplier' => '1.5000',
            'amount' => '2000.0000',
            'status' => 'approved',
        ]);

        $advance = EmployeeAdvance::create([
            'transaction_uuid' => 'abababab-abab-4aba-8aba-abababababab',
            'station_id' => $station->id,
            'employee_id' => $employee->id,
            'advance_date' => '2026-09-10',
            'amount' => '20000.0000',
            'balance' => '20000.0000',
            'status' => 'open',
        ]);

        /** @var PayrollService $service */
        $service = app(PayrollService::class);
        $slip = $service->generateSlip($run->id, $employee->id);

        $this->assertSame('112000.0000', $slip->gross_amount);
        $this->assertSame('5000.0000', $slip->deduction_amount);
        $this->assertSame('107000.0000', $slip->net_amount);
        $this->assertSame(4, $slip->lines->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payroll_slip.generated',
            'auditable_type' => 'App\\Models\\PayrollSlip',
            'auditable_id' => $slip->id,
            'station_id' => $station->id,
        ]);

        $slip = $service->addAdvanceRepayment(
            $slip->id,
            $advance->id,
            '7000.0000',
            '2026-09-30'
        );

        $this->assertSame('112000.0000', $slip->gross_amount);
        $this->assertSame('12000.0000', $slip->deduction_amount);
        $this->assertSame('100000.0000', $slip->net_amount);
        $this->assertSame('13000.0000', $advance->fresh()->balance);
        $this->assertSame(5, $slip->lines->count());
        $this->assertSame('deduction', $slip->lines->last()->line_type);
        $this->assertSame('7000.0000', $slip->lines->last()->amount);

        $slip = $service->approveSlip($slip->id);

        $this->assertSame('approved', $slip->status);
        $this->assertNull($slip->journal_entry_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'employee_advance.repayment_added',
            'auditable_type' => 'App\\Models\\EmployeeAdvance',
            'auditable_id' => $advance->id,
            'station_id' => $station->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payroll_slip.approved',
            'auditable_type' => 'App\\Models\\PayrollSlip',
            'auditable_id' => $slip->id,
            'station_id' => $station->id,
        ]);
    }

    public function test_posting_creates_balanced_payroll_entry_with_employee_dimension(): void
    {
        [$station, $employee, $run, $accounts] = $this->makePayrollContext();

        $advance = EmployeeAdvance::create([
            'transaction_uuid' => 'cdcdcdcd-cdcd-4cdc-8dcd-cdcdcdcdcdcd',
            'station_id' => $station->id,
            'employee_id' => $employee->id,
            'advance_date' => '2026-09-10',
            'amount' => '10000.0000',
            'balance' => '10000.0000',
            'status' => 'open',
        ]);

        /** @var PayrollService $service */
        $service = app(PayrollService::class);
        $slip = $service->generateSlip($run->id, $employee->id);
        $slip = $service->addAdvanceRepayment($slip->id, $advance->id, '5000.0000', '2026-09-30');
        $slip = $service->approveSlip($slip->id);
        $slip = $service->postSlipToAccounting($slip->id);

        $entry = $slip->journalEntry()->with('lines')->first();
        $this->assertNotNull($entry);
        $this->assertSame('posted', $entry->status);
        $debitTotal = '0.0000';
        $creditTotal = '0.0000';
        foreach ($entry->lines as $line) {
            $debitTotal = Decimal::add($debitTotal, $line->debit);
            $creditTotal = Decimal::add($creditTotal, $line->credit);
        }
        $this->assertSame('100000.0000', $debitTotal);
        $this->assertSame('100000.0000', $creditTotal);
        $this->assertSame(3, $entry->lines->count());
        $this->assertSame($employee->id, $entry->lines->first()->employee_id);
        $this->assertSame($slip->id, $entry->source_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payroll_slip.posted',
            'auditable_type' => 'App\\Models\\PayrollSlip',
            'auditable_id' => $slip->id,
            'station_id' => $station->id,
        ]);
    }

    public function test_payment_is_idempotent_and_void_restores_payable(): void
    {
        [$station, $employee, $run, $accounts] = $this->makePayrollContext();

        /** @var PayrollService $service */
        $service = app(PayrollService::class);
        $slip = $service->generateSlip($run->id, $employee->id);
        $slip = $service->approveSlip($slip->id);
        $slip = $service->postSlipToAccounting($slip->id);

        $uuid = 'efefefef-efef-4efe-8fef-efefefefefef';

        $payment = $service->paySlip(
            $slip->id,
            $accounts['cash']->id,
            $uuid,
            '2026-09-30 18:00:00',
            'bank'
        );

        $replay = $service->paySlip(
            $slip->id,
            $accounts['cash']->id,
            $uuid,
            '2026-09-30 18:00:00',
            'bank'
        );

        $this->assertSame($payment->id, $replay->id);
        $this->assertSame('paid', $slip->fresh()->status);
        $this->assertSame('100000.0000', $payment->amount);
        $this->assertSame('400000.0000', $accounts['cash']->fresh()->balance);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payroll_payment.posted',
            'auditable_type' => 'App\\Models\\PayrollPayment',
            'auditable_id' => $payment->id,
            'station_id' => $station->id,
        ]);

        $voided = $service->voidPayment($payment->id, '2026-09-30 20:00:00');

        $this->assertSame('voided', $voided->status);
        $this->assertNotNull($voided->reversal_journal_entry_id);
        $this->assertSame('approved', $slip->fresh()->status);
        $this->assertSame('500000.0000', $accounts['cash']->fresh()->balance);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payroll_payment.voided',
            'auditable_type' => 'App\\Models\\PayrollPayment',
            'auditable_id' => $voided->id,
            'station_id' => $station->id,
        ]);

        $replacement = $service->paySlip(
            $slip->id,
            $accounts['cash']->id,
            '56565656-5656-4565-8565-565656565656',
            '2026-09-30 20:30:00',
            'bank'
        );

        $this->assertSame('100000.0000', $replacement->amount);
        $this->assertSame('400000.0000', $accounts['cash']->fresh()->balance);
        $this->assertSame('paid', $slip->fresh()->status);

        $this->expectException(PayrollException::class);
        $service->paySlip(
            $slip->id,
            $accounts['cash']->id,
            '34343434-3434-4343-8343-343434343434',
            '2026-09-30 21:00:00',
            'bank'
        );
    }

    private function makePayrollContext(): array
    {
        $station = Station::create([
            'code' => 'PRS',
            'name' => 'Payroll Station',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $employee = Employee::create([
            'station_id' => $station->id,
            'employee_no' => 'EMP-100',
            'name' => 'Payroll Employee',
            'status' => 'active',
            'joined_on' => '2026-01-01',
        ]);

        EmployeeContract::create([
            'employee_id' => $employee->id,
            'starts_on' => '2026-01-01',
            'base_salary' => '100000.0000',
            'pay_frequency' => 'monthly',
            'contract_type' => 'permanent',
            'status' => 'active',
        ]);

        $payrollPeriod = PayrollPeriod::create([
            'station_id' => $station->id,
            'code' => '2026-09',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'open',
        ]);

        $fiscalPeriod = FiscalPeriod::create([
            'station_id' => $station->id,
            'name' => '2026-09',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'open',
        ]);

        $run = PayrollRun::create([
            'station_id' => $station->id,
            'payroll_period_id' => $payrollPeriod->id,
            'run_at' => '2026-09-30 17:00:00',
            'status' => 'draft',
        ]);

        $salaryExpense = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '6100',
            'name' => 'Salary Expense',
            'type' => 'expense',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $payable = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '2100',
            'name' => 'Employee Payables',
            'type' => 'liability',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $advanceAccount = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '1200',
            'name' => 'Employee Advances',
            'type' => 'asset',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $tax = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '2200',
            'name' => 'Payroll Tax Payable',
            'type' => 'liability',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $bank = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '1110',
            'name' => 'Bank',
            'type' => 'asset',
            'is_postable' => true,
            'is_active' => true,
        ]);

        EmployeeAccountLink::create([
            'station_id' => $station->id,
            'employee_id' => $employee->id,
            'payable_account_id' => $payable->id,
            'salary_expense_account_id' => $salaryExpense->id,
            'employee_advance_account_id' => $advanceAccount->id,
        ]);

        $cash = CashAccount::create([
            'station_id' => $station->id,
            'code' => 'BANK-PR',
            'name' => 'Payroll Bank',
            'type' => 'bank',
            'account_id' => $bank->id,
            'balance' => '500000.0000',
            'is_active' => true,
        ]);

        return [$station, $employee, $run, [
            'salaryExpense' => $salaryExpense,
            'payable' => $payable,
            'advance' => $advanceAccount,
            'tax' => $tax,
            'bank' => $bank,
            'cash' => $cash,
            'fiscalPeriod' => $fiscalPeriod,
        ]];
    }
}
