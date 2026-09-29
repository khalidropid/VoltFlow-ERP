<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\SupplierInvoiceResource\Pages;
use App\Models\SupplierInvoice;
use App\Support\Decimal;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class SupplierInvoiceResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=SupplierInvoice::class;
    protected static string $viewPermission='procurement.view';
    protected static string $managePermission='procurement.manage';
    protected static ?string $navigationIcon='heroicon-o-document-plus';
    protected static ?string $navigationGroup='المشتريات والمخزون';
    protected static ?int $navigationSort=80;
    public static function getNavigationLabel(): string { return 'فواتير الموردين'; }
    public static function getModelLabel(): string { return 'فاتورة مورد'; }
    public static function getPluralModelLabel(): string { return 'فواتير الموردين'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('number')->label('الرقم')->searchable()->sortable(),
        Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
        Tables\Columns\TextColumn::make('invoice_date')->label('التاريخ')->date()->sortable(),
        Tables\Columns\TextColumn::make('total')->label('الإجمالي')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('paid_amount')->label('المدفوع')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('outstanding')->label('المتبقي')
            ->state(fn(SupplierInvoice $record): string => Decimal::sub((string)$record->total,(string)$record->paid_amount))
            ->numeric(decimalPlaces:4),
        Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
            'gray'=>'draft','success'=>'posted','warning'=>'partially_paid','primary'=>'paid','danger'=>'voided'
        ]),
    ])->actions([])->defaultSort('invoice_date','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListSupplierInvoices::route('/')]; }
}
