<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\WarehouseStockResource\Pages;
use App\Models\WarehouseStock;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class WarehouseStockResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=WarehouseStock::class;
    protected static string $viewPermission='inventory.view';
    protected static string $managePermission='inventory.manage';
    protected static ?string $navigationIcon='heroicon-o-archive-box';
    protected static ?string $navigationGroup='المشتريات والمخزون';
    protected static ?int $navigationSort=30;
    public static function getNavigationLabel(): string { return 'أرصدة المخزون'; }
    public static function getModelLabel(): string { return 'رصيد مخزون'; }
    public static function getPluralModelLabel(): string { return 'أرصدة المخزون'; }
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $stationId=app(\App\Support\StationContext::class)->currentId();
        $query=parent::getEloquentQuery()->join('warehouses','warehouses.id','=','warehouse_stocks.warehouse_id')
            ->select('warehouse_stocks.*');
        return $stationId ? $query->where('warehouses.station_id',$stationId) : $query->whereRaw('1=0');
    }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('warehouse.name')->label('المستودع')->searchable(),
        Tables\Columns\TextColumn::make('item.name')->label('الصنف')->searchable(),
        Tables\Columns\TextColumn::make('quantity')->label('الكمية')->numeric(decimalPlaces:4)->sortable(),
        Tables\Columns\TextColumn::make('average_cost')->label('متوسط التكلفة')->numeric(decimalPlaces:4),
    ])->defaultSort('warehouse_id'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListWarehouseStocks::route('/')]; }
}
