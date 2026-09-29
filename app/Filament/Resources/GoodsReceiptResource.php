<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\GoodsReceiptResource\Pages;
use App\Models\GoodsReceipt;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class GoodsReceiptResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=GoodsReceipt::class;
    protected static string $viewPermission='procurement.view';
    protected static string $managePermission='procurement.manage';
    protected static ?string $navigationIcon='heroicon-o-inbox-arrow-down';
    protected static ?string $navigationGroup='المشتريات والمخزون';
    protected static ?int $navigationSort=70;
    public static function getNavigationLabel(): string { return 'استلامات المشتريات'; }
    public static function getModelLabel(): string { return 'استلام'; }
    public static function getPluralModelLabel(): string { return 'استلامات المشتريات'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('number')->label('الرقم')->searchable()->sortable(),
        Tables\Columns\TextColumn::make('purchaseOrder.number')->label('أمر الشراء'),
        Tables\Columns\TextColumn::make('warehouse.name')->label('المستودع')->searchable(),
        Tables\Columns\TextColumn::make('receipt_date')->label('التاريخ')->date()->sortable(),
        Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors(['gray'=>'draft','success'=>'posted','danger'=>'voided']),
    ])->actions([])->defaultSort('receipt_date','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListGoodsReceipts::route('/')]; }
}
