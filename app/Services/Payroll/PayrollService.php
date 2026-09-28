<?php

namespace App\Services\Payroll;

use App\Models\AdvanceRepayment;
use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeAccountLink;
use App\Models\EmployeeAdvance;
use App\Models\FiscalPeriod;
use App\Models\OvertimeRecord;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Services\Accounting\JournalEntryService;
use App\Support\Decimal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}

    public function generateSlip(
        int $payrollRunId,
        int $employeeId,
        ?string $slipNumber = null
    ): PayrollSlip {
        return DB::transaction(function () use ($payrollRunId, $employeeId, $slipNumber) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($payrollRunId);
            $period = $run->payrollPeriod()->firstOrFail();

            if (!in_array($run->status, ['draft', 'processing'], true)) {
                throw new PayrollException('Payroll run is not editable in its current status.');
            }

            if ($period->status !== 'open') {
                throw new PayrollException('Payroll period must be open.');
            }

            $employee = Employee::query()->with('contracts')
                ->whereKey($employeeId)
                ->where('station_id', $run->station_id)
                ->firstOrFail();

            $existing = PayrollSlip::query()
                ->where('payroll_run_id', $run->id)
                ->where('employee_id', $employee->id)
                ->first();

            if ($existing) {
                return $existing->load('lines.salaryComponent');
            }

            $contract = $employee->contracts()
                ->where('starts_on', '<=', $period->ends_on)
                ->where(function ($q) use ($period) {
                    $q->whereNull('ends_on')->orWhere('ends_on', '>=', $period->starts_on);
                })
                ->whereIn('status', ['active', 'expired'])
                ->orderByDesc('starts_on')
                ->first();

            if (!$contract) {
                throw new PayrollException('No applicable employee contract was found for the payroll period.');
            }

            $gross = Decimal::normalize($contract->base_salary);
            $deductions = '0.0000';
            $lines = [[
                'salary_component_id' => null,
                'description' => 'Base salary',
                'line_type' => 'earning',
                'amount' => $gross,
            ]];

            $components = $employee->salaryComponents()
                ->with('salaryComponent')
                ->where('is_active', true)
                ->where('effective_from', '<=', $period->ends_on)
                ->where(function ($q) use ($period) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $period->starts_on);
                })
                ->get();

            foreach ($components as $assignment) {
                $component = $assignment->salaryComponent;
                if (!$component || $component->station_id !== $run->station_id) {
                    throw new PayrollException('Employee salary component belongs to another station or is missing.');
                }

                $amount = Decimal::normalize($assignment->amount);
                if ($component->type === 'earning') {
                    $gross = Decimal::add($gross, $amount);
                    $lines[] = [
                        'salary_component_id' => $component->id,
                        'description' => $component->name,
                        'line_type' => 'earning',
                        'amount' => $amount,
                    ];
                } else {
                    $deductions = Decimal::add($deductions, $amount);
                    $lines[] = [
                        'salary_component_id' => $component->id,
                        'description' => $component->name,
                        'line_type' => 'deduction',
                        'amount' => $amount,
                    ];
                }
            }

            $overtime = OvertimeRecord::query()
                ->where('station_id', $run->station_id)
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$period->starts_on, $period->ends_on])
                ->where('status', 'approved')
                ->whereNull('payroll_slip_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($overtime as $record) {
                $amount = Decimal::normalize($record->amount);
                $gross = Decimal::add($gross, $amount);
                $lines[] = [
                    'salary_component_id' => null,
                    'description' => 'Approved overtime',
                    'line_type' => 'earning',
                    'amount' => $amount,
                ];
            }

            if (Decimal::compare($deductions, $gross) > 0) {
                throw new PayrollException('Total payroll deductions cannot exceed gross pay.');
            }

            $net = Decimal::sub($gross, $deductions);

            $slip = PayrollSlip::create([
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'slip_number' => $slipNumber ?: sprintf('PS-%d-%s', $run->id, $employee->employee_no),
                'gross_amount' => $gross,
                'deduction_amount' => $deductions,
                'net_amount' => $net,
                'status' => 'draft',
            ]);

            foreach ($lines as $line) {
                $slip->lines()->create($line);
            }

            foreach ($overtime as $record) {
                $record->update(['payroll_slip_id' => $slip->id]);
            }

            return $slip->load(['lines.salaryComponent', 'employee', 'payrollRun.payrollPeriod']);
        });
    }

    public function addAdvanceRepayment(
        int $payrollSlipId,
        int $employeeAdvanceId,
        string $amount,
        string $repaymentDate
    ): PayrollSlip {
        return DB::transaction(function () use ($payrollSlipId, $employeeAdvanceId, $amount, $repaymentDate) {
            $slip = PayrollSlip::query()->lockForUpdate()->findOrFail($payrollSlipId);
            if (!in_array($slip->status, ['draft', 'approved'], true) || $slip->journal_entry_id) {
                throw new PayrollException('Advance repayments can only be added before payroll accounting is posted.');
            }

            $advance = EmployeeAdvance::query()->lockForUpdate()->findOrFail($employeeAdvanceId);
            $employee = $slip->employee()->firstOrFail();

            if ($advance->employee_id !== $employee->id || $advance->station_id !== $employee->station_id) {
                throw new PayrollException('Employee advance does not belong to the payroll employee and station.');
            }

            $normalized = Decimal::normalize($amount);
            if (Decimal::compare($normalized, '0.0000') <= 0) {
                throw new PayrollException('Advance repayment must be greater than zero.');
            }

            if (Decimal::compare($normalized, $advance->balance) > 0) {
                throw new PayrollException('Advance repayment cannot exceed the outstanding advance balance.');
            }

            AdvanceRepayment::create([
                'employee_advance_id' => $advance->id,
                'payroll_slip_id' => $slip->id,
                'repayment_date' => $repaymentDate,
                'amount' => $normalized,
            ]);

            $newBalance = Decimal::sub($advance->balance, $normalized);
            $advance->update([
                'balance' => $newBalance,
                'status' => Decimal::compare($newBalance, '0.0000') === 0 ? 'settled' : 'open',
            ]);

            $this->recalculateSlip($slip);

            return $slip->fresh(['lines.salaryComponent', 'advanceRepayments.employeeAdvance']);
        });
    }

    public function approveSlip(int $payrollSlipId): PayrollSlip
    {
        return DB::transaction(function () use ($payrollSlipId) {
            $slip = PayrollSlip::query()->lockForUpdate()->findOrFail($payrollSlipId);
            if ($slip->journal_entry_id) {
                throw new PayrollException('Payroll accounting is already posted.');
            }
            if ($slip->status !== 'draft') {
                throw new PayrollException('Only draft payroll slips can be approved.');
            }

            $this->recalculateSlip($slip);
            if (Decimal::compare($slip->net_amount, '0.0000') < 0) {
                throw new PayrollException('Net salary cannot be negative.');
            }

            $slip->update(['status' => 'approved']);

            return $slip->fresh(['lines.salaryComponent', 'advanceRepayments']);
        });
    }

    public function postSlipToAccounting(int $payrollSlipId, ?User $actor = null): PayrollSlip
    {
        return DB::transaction(function () use ($payrollSlipId, $actor) {
            $slip = PayrollSlip::query()->lockForUpdate()->findOrFail($payrollSlipId);
            if ($slip->journal_entry_id) {
                return $slip->fresh(['journalEntry.lines']);
            }
            if ($slip->status !== 'approved') {
                throw new PayrollException('Only approved payroll slips can be posted to accounting.');
            }

            $run = $slip->payrollRun()->firstOrFail();
            $payrollPeriod = $run->payrollPeriod()->firstOrFail();
            if ($payrollPeriod->status !== 'open') {
                throw new PayrollException('Payroll period must be open for posting.');
            }

            $period = FiscalPeriod::query()
                ->where('station_id', $run->station_id)
                ->where('status', 'open')
                ->where('starts_on', '<=', $payrollPeriod->ends_on)
                ->where('ends_on', '>=', $payrollPeriod->ends_on)
                ->first();

            if (!$period) {
                throw new PayrollException('No open fiscal period covers the payroll posting date.');
            }

            $employee = $slip->employee()->firstOrFail();
            $link = EmployeeAccountLink::query()
                ->with(['payableAccount', 'salaryExpenseAccount', 'employeeAdvanceAccount'])
                ->where('station_id', $run->station_id)
                ->where('employee_id', $employee->id)
                ->first();

            if (!$link) {
                throw new PayrollException('Employee accounting link is missing.');
            }

            $this->assertPostableAccount($link->salaryExpenseAccount, $run->station_id, 'Salary expense account');
            $this->assertPostableAccount($link->payableAccount, $run->station_id, 'Employee payable account');

            $slip->load(['lines.salaryComponent.account', 'advanceRepayments']);

            $advanceTotal = '0.0000';
            foreach ($slip->advanceRepayments as $repayment) {
                $advanceTotal = Decimal::add($advanceTotal, $repayment->amount);
            }

            if (Decimal::compare($advanceTotal, '0.0000') > 0) {
                $this->assertPostableAccount($link->employeeAdvanceAccount, $run->station_id, 'Employee advance account');
            }

            $otherDeductions = '0.0000';
            foreach ($slip->lines as $line) {
                if ($line->line_type !== 'deduction') {
                    continue;
                }
                $isAdvance = strcasecmp(trim($line->description), 'Advance repayment') === 0;
                if ($isAdvance) {
                    continue;
                }

                $component = $line->salaryComponent;
                if (!$component || $component->type !== 'deduction') {
                    throw new PayrollException('Every non-advance deduction must reference a deduction salary component.');
                }
                $this->assertPostableAccount($component->account, $run->station_id, 'Deduction account');
                $otherDeductions = Decimal::add($otherDeductions, $line->amount);
            }

            $gross = Decimal::normalize($slip->gross_amount);
            $net = Decimal::normalize($slip->net_amount);
            $credits = '0.0000';
            $lines = [[
                'account_id' => $link->salary_expense_account_id,
                'debit' => $gross,
                'credit' => '0.0000',
                'description' => 'Payroll gross salary',
                'employee_id' => $employee->id,
            ]];

            if (Decimal::compare($advanceTotal, '0.0000') > 0) {
                $lines[] = [
                    'account_id' => $link->employee_advance_account_id,
                    'debit' => '0.0000',
                    'credit' => $advanceTotal,
                    'description' => 'Employee advance repayment',
                    'employee_id' => $employee->id,
                ];
                $credits = Decimal::add($credits, $advanceTotal);
            }

            foreach ($slip->lines as $line) {
                if ($line->line_type !== 'deduction' || strcasecmp(trim($line->description), 'Advance repayment') === 0) {
                    continue;
                }
                $component = $line->salaryComponent;
                $lines[] = [
                    'account_id' => $component->account_id,
                    'debit' => '0.0000',
                    'credit' => Decimal::normalize($line->amount),
                    'description' => $line->description,
                    'employee_id' => $employee->id,
                ];
                $credits = Decimal::add($credits, Decimal::normalize($line->amount));
            }

            $lines[] = [
                'account_id' => $link->payable_account_id,
                'debit' => '0.0000',
                'credit' => $net,
                'description' => 'Net salary payable',
                'employee_id' => $employee->id,
            ];
            $credits = Decimal::add($credits, $net);

            if ($credits !== $gross) {
                throw new PayrollException('Payroll accounting lines are not balanced with gross pay.');
            }

            $entry = $this->journalEntryService->createAndPost(
                $run->station_id,
                $period->id,
                'PAYROLL-' . $slip->slip_number,
                $period->ends_on->format('Y-m-d'),
                'Payroll accounting for ' . $slip->slip_number,
                $lines,
                $actor,
                'payroll_slip',
                $slip->id
            );

            $slip->update(['journal_entry_id' => $entry->id]);

            return $slip->fresh(['journalEntry.lines']);
        });
    }

    public function paySlip(
        int $payrollSlipId,
        int $cashAccountId,
        string $transactionUuid,
        string $paidAt,
        string $method = 'bank',
        ?User $actor = null
    ): PayrollPayment {
        return DB::transaction(function () use ($payrollSlipId, $cashAccountId, $transactionUuid, $paidAt, $method, $actor) {
            $existing = PayrollPayment::query()->where('transaction_uuid', $transactionUuid)->first();
            if ($existing) {
                if ($existing->payroll_slip_id !== $payrollSlipId) {
                    throw new PayrollException('Transaction UUID is already used for another payroll payment.');
                }
                if (Decimal::compare($existing->amount, PayrollSlip::query()->findOrFail($payrollSlipId)->net_amount) !== 0) {
                    throw new PayrollException('Transaction UUID is already used with a different payroll amount.');
                }
                return $existing->load(['payrollSlip', 'cashAccount', 'journalEntry']);
            }

            $slip = PayrollSlip::query()->lockForUpdate()->findOrFail($payrollSlipId);
            if ($slip->status !== 'approved' || !$slip->journal_entry_id) {
                throw new PayrollException('Payroll slip must be approved and posted before payment.');
            }

            $alreadyPaid = PayrollPayment::query()->where('payroll_slip_id', $slip->id)->first();
            if ($alreadyPaid) {
                throw new PayrollException('Payroll slip already has a payment.');
            }

            $cash = CashAccount::query()->with('glAccount')->lockForUpdate()->findOrFail($cashAccountId);
            if ($cash->station_id !== $slip->payrollRun()->firstOrFail()->station_id || !$cash->is_active) {
                throw new PayrollException('Cash account does not belong to the payroll station or is inactive.');
            }
            $this->assertPostableAccount($cash->glAccount, $cash->station_id, 'Cash/bank GL account');

            $run = $slip->payrollRun()->firstOrFail();
            $period = FiscalPeriod::query()
                ->where('station_id', $run->station_id)
                ->where('status', 'open')
                ->where('starts_on', '<=', substr($paidAt, 0, 10))
                ->where('ends_on', '>=', substr($paidAt, 0, 10))
                ->first();

            if (!$period) {
                throw new PayrollException('No open fiscal period covers the payroll payment date.');
            }

            $link = EmployeeAccountLink::query()
                ->where('station_id', $run->station_id)
                ->where('employee_id', $slip->employee_id)
                ->first();
            if (!$link) {
                throw new PayrollException('Employee accounting link is missing.');
            }
            $this->assertPostableAccount($link->payableAccount()->firstOrFail(), $run->station_id, 'Employee payable account');

            $amount = Decimal::normalize($slip->net_amount);
            if (Decimal::compare($amount, '0.0000') === 0) {
                throw new PayrollException('Zero-value payroll payments are not allowed.');
            }

            $payment = PayrollPayment::create([
                'transaction_uuid' => $transactionUuid,
                'station_id' => $run->station_id,
                'payroll_slip_id' => $slip->id,
                'cash_account_id' => $cash->id,
                'paid_at' => $paidAt,
                'amount' => $amount,
                'method' => $method,
                'status' => 'posted',
            ]);

            $entry = $this->journalEntryService->createAndPost(
                $run->station_id,
                $period->id,
                'PAYMENT-' . str_replace('-', '', $transactionUuid),
                substr($paidAt, 0, 10),
                'Payroll payment ' . $slip->slip_number,
                [
                    [
                        'account_id' => $link->payable_account_id,
                        'debit' => $amount,
                        'credit' => '0.0000',
                        'description' => 'Settle employee payroll payable',
                        'employee_id' => $slip->employee_id,
                    ],
                    [
                        'account_id' => $cash->account_id,
                        'debit' => '0.0000',
                        'credit' => $amount,
                        'description' => 'Payroll cash/bank payment',
                        'employee_id' => $slip->employee_id,
                    ],
                ],
                $actor,
                'payroll_payment',
                $payment->id
            );

            $payment->update(['journal_entry_id' => $entry->id]);
            $slip->update(['status' => 'paid']);

            return $payment->fresh(['payrollSlip', 'cashAccount', 'journalEntry']);
        });
    }

    public function voidPayment(
        int $payrollPaymentId,
        string $voidedAt,
        ?User $actor = null
    ): PayrollPayment {
        return DB::transaction(function () use ($payrollPaymentId, $voidedAt, $actor) {
            $payment = PayrollPayment::query()->lockForUpdate()->findOrFail($payrollPaymentId);
            if ($payment->status === 'voided') {
                return $payment->fresh(['journalEntry', 'reversalJournalEntry', 'payrollSlip']);
            }
            if ($payment->status !== 'posted' || !$payment->journal_entry_id) {
                throw new PayrollException('Only posted payroll payments with accounting can be voided.');
            }

            $slip = $payment->payrollSlip()->firstOrFail();
            $run = $slip->payrollRun()->firstOrFail();
            $period = FiscalPeriod::query()
                ->where('station_id', $run->station_id)
                ->where('status', 'open')
                ->where('starts_on', '<=', substr($voidedAt, 0, 10))
                ->where('ends_on', '>=', substr($voidedAt, 0, 10))
                ->first();

            if (!$period) {
                throw new PayrollException('No open fiscal period covers the payroll payment reversal date.');
            }

            $link = EmployeeAccountLink::query()
                ->where('station_id', $run->station_id)
                ->where('employee_id', $slip->employee_id)
                ->firstOrFail();
            $cash = $payment->cashAccount()->with('glAccount')->firstOrFail();

            $entry = $this->journalEntryService->createAndPost(
                $run->station_id,
                $period->id,
                'REVERSAL-PAYMENT-' . $payment->id,
                substr($voidedAt, 0, 10),
                'Reverse payroll payment ' . $slip->slip_number,
                [
                    [
                        'account_id' => $cash->account_id,
                        'debit' => $payment->amount,
                        'credit' => '0.0000',
                        'description' => 'Reverse payroll cash/bank payment',
                        'employee_id' => $slip->employee_id,
                    ],
                    [
                        'account_id' => $link->payable_account_id,
                        'debit' => '0.0000',
                        'credit' => $payment->amount,
                        'description' => 'Restore employee payroll payable',
                        'employee_id' => $slip->employee_id,
                    ],
                ],
                $actor,
                'payroll_payment_reversal',
                $payment->id
            );

            $payment->update([
                'status' => 'voided',
                'reversal_journal_entry_id' => $entry->id,
                'voided_at' => $voidedAt,
                'voided_by' => $actor?->id,
            ]);
            $slip->update(['status' => 'approved']);

            return $payment->fresh(['journalEntry', 'reversalJournalEntry', 'payrollSlip']);
        });
    }

    private function recalculateSlip(PayrollSlip $slip): void
    {
        $slip->load('lines');
        $gross = '0.0000';
        $deductions = '0.0000';

        foreach ($slip->lines as $line) {
            $amount = Decimal::normalize($line->amount);
            if ($line->line_type === 'earning') {
                $gross = Decimal::add($gross, $amount);
            } elseif ($line->line_type === 'deduction') {
                $deductions = Decimal::add($deductions, $amount);
            } else {
                throw new PayrollException('Payroll slip line type is invalid.');
            }
        }

        if (Decimal::compare($deductions, $gross) > 0) {
            throw new PayrollException('Total payroll deductions cannot exceed gross pay.');
        }

        $slip->update([
            'gross_amount' => $gross,
            'deduction_amount' => $deductions,
            'net_amount' => Decimal::sub($gross, $deductions),
        ]);
    }

    private function assertPostableAccount($account, int $stationId, string $label): void
    {
        if (!$account || !$account->is_active || !$account->is_postable ||
            !($account->station_id === null || (int) $account->station_id === $stationId)) {
            throw new PayrollException($label . ' is not active/postable for the payroll station.');
        }
    }
}
