<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseOrderResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = PurchaseOrder::class;
    protected static string $viewPermission = 'procurement.view';
    protected static string $managePermission = 'procurement.manage';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';
    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string { return 'أوامر الشراء'; }
    public static function getModelLabel(): string { return 'أمر شراء'; }
    public static function getPluralModelLabel(): string { return 'أوامر الشراء'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();

        return $form->schema([
            Forms\Components\TextInput::make('number')->label('رقم الأمر')->required()->maxLength(50),
            Forms\Components\Select::make('supplier_id')->label('المورد')
                ->options(fn()=>Supplier::query()->where('station_id',$stationId??0)->orderBy('name')->pluck('name','id')->all())
                ->required()->searchable()->native(false),
            Forms\Components\Select::make('purchase_request_id')->label('طلب الشراء')
                ->options(fn()=>PurchaseRequest::query()->where('station_id',$stationId??0)->orderBy('number')->pluck('number','id')->all())
                ->searchable()->native(false),
            Forms\Components\DatePicker::make('order_date')->label('التاريخ')->required()->default(now()),
            Forms\Components\Select::make('status')->label('الحالة')->options([
                'draft'=>'مسودة','approved'=>'معتمد','sent'=>'مرسل','partially_received'=>'مستلم جزئيًا','received'=>'مستلم','cancelled'=>'ملغى',
            ])->required()->default('draft')->native(false),
            Forms\Components\TextInput::make('total')->label('الإجمالي')->numeric()->default(0)->required(),
            Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
            Forms\Components\Repeater::make('items')->label('الأصناف')->relationship()
                ->schema([
                    Forms\Components\Select::make('item_id')
                        ->label('الصنف')
                        ->options(fn()=>Item::query()->where('station_id',$stationId??0)->orderBy('name')->pluck('name','id')->all())
                        ->required()->searchable()->native(false),
                    Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()->required()->minValue(0.0001),
                    Forms\Components\TextInput::make('unit_cost')->label('تكلفة الوحدة')->numeric()->required()->minValue(0),
                    Forms\Components\TextInput::make('amount')->label('المبلغ')->numeric()->required()->minValue(0),
                ])->columns(4)->minItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('الرقم')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
            Tables\Columns\TextColumn::make('order_date')->label('التاريخ')->date()->sortable(),
            Tables\Columns\TextColumn::make('total')->label('الإجمالي')->numeric(decimalPlaces:4),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
                'gray'=>'draft','success'=>'approved','info'=>'sent','warning'=>'partially_received','primary'=>'received','danger'=>'cancelled',
            ]),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListPurchaseOrders::route('/'),
            'create'=>Pages\CreatePurchaseOrder::route('/create'),
            'edit'=>Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
