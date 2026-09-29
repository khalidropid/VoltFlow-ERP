<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\SupplierInvoiceResource\Pages;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Support\Decimal;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class SupplierInvoiceResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = SupplierInvoice::class;
    protected static string $viewPermission = 'procurement.view';
    protected static string $managePermission = 'procurement.manage';
    protected static ?string $navigationIcon = 'heroicon-o-document-plus';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';
    protected static ?int $navigationSort = 80;

    public static function getNavigationLabel(): string { return 'فواتير الموردين'; }
    public static function getModelLabel(): string { return 'فاتورة مورد'; }
    public static function getPluralModelLabel(): string { return 'فواتير الموردين'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();

        return $form->schema([
            Forms\Components\TextInput::make('number')->label('رقم الفاتورة')->required()->maxLength(80),
            Forms\Components\Select::make('supplier_id')->label('المورد')
                ->options(fn () => Supplier::query()->where('station_id',$stationId ?? 0)
                    ->where('status','active')->orderBy('name')->pluck('name','id')->all())
                ->required()->searchable()->native(false),
            Forms\Components\Select::make('goods_receipt_id')->label('استلام مرتبط')
                ->options(fn () => GoodsReceipt::query()->where('station_id',$stationId ?? 0)
                    ->where('status','posted')->orderByDesc('receipt_date')->pluck('number','id')->all())
                ->searchable()->native(false),
            Forms\Components\DatePicker::make('invoice_date')->label('تاريخ الفاتورة')->required()->default(now()),
            Forms\Components\DatePicker::make('due_date')->label('تاريخ الاستحقاق'),
            Forms\Components\TextInput::make('subtotal')->label('الإجمالي قبل الضريبة')->numeric()->required()->default(0)->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $set('total', Decimal::add((string) $get('subtotal'), (string) $get('tax')));
                }),
            Forms\Components\TextInput::make('tax')->label('الضريبة')->numeric()->required()->default(0)->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $set('total', Decimal::add((string) $get('subtotal'), (string) $get('tax')));
                }),
            Forms\Components\TextInput::make('total')->label('الإجمالي')->numeric()->required()->default(0)->disabled()->dehydrated(),
            Forms\Components\Repeater::make('items')->label('بنود الفاتورة')->relationship()
                ->schema([
                    Forms\Components\Select::make('item_id')->label('الصنف')
                        ->options(fn () => Item::query()->where('station_id',$stationId ?? 0)
                            ->where('is_active',true)->orderBy('name')->pluck('name','id')->all())
                        ->required()->searchable()->native(false),
                    Forms\Components\TextInput::make('description')->label('الوصف')->maxLength(500),
                    Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()->required()->minValue(0.0001)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('amount', Decimal::multiply((string) $get('quantity'), (string) $get('unit_cost')));
                        }),
                    Forms\Components\TextInput::make('unit_cost')->label('تكلفة الوحدة')->numeric()->required()->minValue(0)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('amount', Decimal::multiply((string) $get('quantity'), (string) $get('unit_cost')));
                        }),
                    Forms\Components\TextInput::make('amount')->label('المبلغ')->numeric()->required()->disabled()->dehydrated(),
                ])->columns(5)->minItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('الرقم')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
            Tables\Columns\TextColumn::make('goodsReceipt.number')->label('الاستلام'),
            Tables\Columns\TextColumn::make('invoice_date')->label('التاريخ')->date()->sortable(),
            Tables\Columns\TextColumn::make('total')->label('الإجمالي')->numeric(decimalPlaces:4),
            Tables\Columns\TextColumn::make('paid_amount')->label('المدفوع')->numeric(decimalPlaces:4),
            Tables\Columns\TextColumn::make('outstanding')->label('المتبقي')
                ->state(fn (SupplierInvoice $record): string => Decimal::sub((string)$record->total,(string)$record->paid_amount))
                ->numeric(decimalPlaces:4),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
                'gray'=>'draft','success'=>'posted','warning'=>'partially_paid','primary'=>'paid','danger'=>'voided'
            ])->formatStateUsing(fn (string $state): string => match($state) {
                'draft'=>'مسودة','posted'=>'مرحل','partially_paid'=>'مدفوعة جزئيًا','paid'=>'مدفوعة','voided'=>'ملغاة',default=>$state,
            }),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn(SupplierInvoice $record): bool => $record->status === 'draft'),
            Action::make('post')
                ->label('ترحيل الفاتورة')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn(SupplierInvoice $record): bool =>
                    (auth()->user()?->can('procurement.manage') ?? false) && $record->status === 'draft')
                ->requiresConfirmation()
                ->action(function(SupplierInvoice $record): void {
                    $stationId = app(StationContext::class)->currentId();
                    abort_unless($stationId && (int)$record->station_id === $stationId, 403);
                    app(\App\Services\Procurement\SupplierInvoiceService::class)->post((int)$record->id, auth()->user());
                    Notification::make()->success()->title('تم ترحيل فاتورة المورد')
                        ->body('تم إنشاء القيد وتحديث الذمة الدائنة.')->send();
                }),
        ])->defaultSort('invoice_date','desc');
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && $record->status === 'draft';
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListSupplierInvoices::route('/'),
            'create'=>Pages\CreateSupplierInvoice::route('/create'),
            'edit'=>Pages\EditSupplierInvoice::route('/{record}/edit'),
        ];
    }
}
