<?php

namespace App\Services\Reports;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StationReportService
{
    public function operationalSnapshot(int $stationId, string $from, string $to): array
    {
        [$start, $end] = $this->dateRange($from, $to);

        return [
            'station_id' => $stationId,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'billing' => $this->billing($stationId, $start, $end),
            'collections' => $this->collections($stationId, $start, $end),
            'generation' => $this->generation($stationId, $start, $end),
            'payroll' => $this->payroll($stationId, $start, $end),
            'inventory' => $this->inventory($stationId),
            'fuel' => $this->fuel($stationId, $start, $end),
        ];
    }

    public function billing(int $stationId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = DB::table('invoices')
            ->where('station_id', $stationId)
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("COUNT(*) as invoice_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status <> 'void' THEN total ELSE 0 END), 0) as billed_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status <> 'void' THEN paid_amount ELSE 0 END), 0) as paid_total")
            ->first();

        $billed = Decimal::normalize((string) ($row->billed_total ?? '0'));
        $paid = Decimal::normalize((string) ($row->paid_total ?? '0'));

        $paidAgainstBilled = Decimal::compare($paid, $billed) > 0 ? $billed : $paid;

        return [
            'invoice_count' => (int) $row->invoice_count,
            'billed_total' => $billed,
            'paid_total' => $paid,
            'outstanding_total' => Decimal::sub($billed, $paidAgainstBilled),
        ];
    }

    public function collections(int $stationId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = DB::table('payments')
            ->where('station_id', $stationId)
            ->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw("COUNT(*) as payment_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'posted' THEN amount ELSE 0 END), 0) as posted_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'voided' THEN amount ELSE 0 END), 0) as voided_total")
            ->first();

        return [
            'payment_count' => (int) $row->payment_count,
            'posted_total' => Decimal::normalize((string) ($row->posted_total ?? '0')),
            'voided_total' => Decimal::normalize((string) ($row->voided_total ?? '0')),
        ];
    }

    public function generation(int $stationId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $readings = DB::table('generation_readings')
            ->where('station_id', $stationId)
            ->whereBetween('reading_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw("COALESCE(SUM(energy_kwh), 0) as energy_kwh")
            ->first();

        $runtime = DB::table('generator_runtime_logs')
            ->where('station_id', $stationId)
            ->where('started_at', '<=', $end->endOfDay())
            ->where(function ($q) use ($start) {
                $q->whereNull('stopped_at')->orWhere('stopped_at', '>=', $start->startOfDay());
            })
            ->selectRaw("COALESCE(SUM(hours), 0) as runtime_hours")
            ->first();

        return [
            'energy_kwh' => Decimal::normalize((string) ($readings->energy_kwh ?? '0')),
            'runtime_hours' => Decimal::normalize((string) ($runtime->runtime_hours ?? '0')),
        ];
    }

    public function payroll(int $stationId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = DB::table('payroll_slips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_slips.payroll_run_id')
            ->where('payroll_runs.station_id', $stationId)
            ->whereBetween('payroll_runs.run_at', [$start->startOfDay(), $end->endOfDay()])
            ->where('payroll_slips.status', '<>', 'voided')
            ->selectRaw("COUNT(*) as slip_count")
            ->selectRaw("COALESCE(SUM(payroll_slips.gross_amount), 0) as gross_total")
            ->selectRaw("COALESCE(SUM(payroll_slips.deduction_amount), 0) as deduction_total")
            ->selectRaw("COALESCE(SUM(payroll_slips.net_amount), 0) as net_total")
            ->first();

        return [
            'slip_count' => (int) $row->slip_count,
            'gross_total' => Decimal::normalize((string) ($row->gross_total ?? '0')),
            'deduction_total' => Decimal::normalize((string) ($row->deduction_total ?? '0')),
            'net_total' => Decimal::normalize((string) ($row->net_total ?? '0')),
        ];
    }

    public function inventory(int $stationId): array
    {
        $row = DB::table('warehouse_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_stocks.warehouse_id')
            ->where('warehouses.station_id', $stationId)
            ->selectRaw("COALESCE(ROUND(SUM(warehouse_stocks.quantity), 4), 0) as quantity")
            ->selectRaw("COALESCE(ROUND(SUM(warehouse_stocks.quantity * warehouse_stocks.average_cost), 4), 0) as stock_value")
            ->first();

        return [
            'quantity' => Decimal::normalize((string) ($row->quantity ?? '0')),
            'stock_value' => Decimal::normalize((string) ($row->stock_value ?? '0')),
        ];
    }

    public function fuel(int $stationId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $receipts = DB::table('fuel_receipts')
            ->where('station_id', $stationId)
            ->whereBetween('received_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw("COALESCE(SUM(quantity), 0) as received_quantity")
            ->selectRaw("COALESCE(SUM(total_cost), 0) as received_cost")
            ->first();

        $issues = DB::table('fuel_issues')
            ->where('station_id', $stationId)
            ->whereBetween('issued_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw("COALESCE(SUM(quantity), 0) as issued_quantity")
            ->selectRaw("COALESCE(SUM(total_cost), 0) as issued_cost")
            ->first();

        return [
            'received_quantity' => Decimal::normalize((string) ($receipts->received_quantity ?? '0')),
            'received_cost' => Decimal::normalize((string) ($receipts->received_cost ?? '0')),
            'issued_quantity' => Decimal::normalize((string) ($issues->issued_quantity ?? '0')),
            'issued_cost' => Decimal::normalize((string) ($issues->issued_cost ?? '0')),
        ];
    }

    private function dateRange(string $from, string $to): array
    {
        try {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end = CarbonImmutable::parse($to)->endOfDay();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Report dates must be valid dates.', 0, $e);
        }

        if ($start->gt($end)) {
            throw new InvalidArgumentException('Report start date cannot be after end date.');
        }

        return [$start, $end];
    }
}
