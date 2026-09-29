<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\SupplierPaymentResource\Pages;
use App\Models\SupplierPayment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class SupplierPaymentResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=SupplierPayment::class;
    protected static string $viewPermission='procurement.view';
    protected static string $managePermission='procurement.manage';
    protected static ?string $navigationIcon='heroicon-o-banknotes';
    protected static ?string $navigationGroup='المشتريات والمخزون';
    protected static ?int $navigationSort=90;
    public static function getNavigationLabel(): string { return 'مدفوعات الموردين'; }
    public static function getModelLabel(): string { return 'دفعة مورد'; }
    public static function getPluralModelLabel(): string { return 'مدفوعات الموردين'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('receipt_number')->label('رقم الإيصال')->searchable()->sortable(),
        Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
        Tables\Columns\TextColumn::make('paid_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
        Tables\Columns\TextColumn::make('amount')->label('المبلغ')->numeric(decimalPlaces:4)->sortable(),
        Tables\Columns\TextColumn::make('method')->label('الطريقة'),
        Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors(['success'=>'posted','danger'=>'voided']),
    ])->actions([])->defaultSort('paid_at','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListSupplierPayments::route('/')]; }
}
