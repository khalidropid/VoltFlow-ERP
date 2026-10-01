<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\FuelReceiptResource\Pages;
use App\Models\FuelReceipt;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class FuelReceiptResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=FuelReceipt::class;
    protected static string $viewPermission='fuel.view';
    protected static string $managePermission='fuel.manage';
    protected static ?string $navigationIcon='heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup='الصيانة والوقود';
    protected static ?int $navigationSort=30;
    public static function getNavigationLabel(): string { return 'استلامات الوقود'; }
    public static function getModelLabel(): string { return 'استلام وقود'; }
    public static function getPluralModelLabel(): string { return 'استلامات الوقود'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('fuelType.name')->label('الوقود')->searchable(),
        Tables\Columns\TextColumn::make('fuelTank.name')->label('الخزان')->searchable(),
        Tables\Columns\TextColumn::make('received_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
        Tables\Columns\TextColumn::make('quantity')->label('الكمية')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('unit_cost')->label('تكلفة الوحدة')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('total_cost')->label('إجمالي التكلفة')->numeric(decimalPlaces:4),
        Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors(['gray'=>'draft','success'=>'posted','danger'=>'voided']),
    ])->defaultSort('received_at','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListFuelReceipts::route('/')]; }
}
