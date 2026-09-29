<?php

namespace App\Filament\Widgets;

use App\Services\Reports\StationReportService;
use App\Support\StationContext;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StationOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $stationId = app(StationContext::class)->currentId();

        if (! $stationId) {
            return [
                Stat::make('المحطة الحالية', 'غير محددة')
                    ->description('لا توجد محطة مرتبطة بحساب المستخدم.'),
            ];
        }

        $end = CarbonImmutable::today();
        $start = $end->startOfMonth();

        $snapshot = app(StationReportService::class)->operationalSnapshot(
            $stationId,
            $start->toDateString(),
            $end->toDateString(),
        );

        return [
            Stat::make('الفوترة الشهرية', $snapshot['billing']['billed_total'])
                ->description('إجمالي الفواتير غير الملغاة')
                ->descriptionIcon('heroicon-m-receipt-percent'),

            Stat::make('التحصيل الشهري', $snapshot['collections']['posted_total'])
                ->description('التحصيلات المرحّلة')
                ->descriptionIcon('heroicon-m-banknotes'),

            Stat::make('التوليد', $snapshot['generation']['energy_kwh'] . ' kWh')
                ->description('الطاقة المولدة خلال الشهر')
                ->descriptionIcon('heroicon-m-bolt'),

            Stat::make('مخزون المستودعات', $snapshot['inventory']['stock_value'])
                ->description('قيمة المخزون الحالية')
                ->descriptionIcon('heroicon-m-archive-box'),
        ];
    }
}
