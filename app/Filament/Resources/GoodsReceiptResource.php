<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\GoodsReceiptResource\Pages;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
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
use Illuminate\Support\Str;

class GoodsReceiptResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = GoodsReceipt::class;
    protected static string $viewPermission = 'procurement.view';
    protected static string $managePermission = 'procurement.manage';
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';
    protected static ?int $navigationSort = 70;

    public static function getNavigationLabel(): string { return 'استلامات المشتريات'; }
    public static function getModelLabel(): string { return 'استلام'; }
    public static function getPluralModelLabel(): string { return 'استلامات المشتريات'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();

        return $form->schema([
            Forms\Components\TextInput::make('number')->label('رقم الاستلام')->required()->maxLength(50),
            Forms\Components\Select::make('purchase_order_id')->label('أمر الشراء')
                ->options(fn () => PurchaseOrder::query()->where('station_id', $stationId ?? 0)
                    ->whereIn('status', ['approved','sent','partially_received'])
                    ->orderByDesc('order_date')->pluck('number','id')->all())
                ->searchable()->native(false),
            Forms\Components\Select::make('warehouse_id')->label('المستودع')
                ->options(fn () => Warehouse::query()->where('station_id', $stationId ?? 0)
                    ->where('is_active', true)->orderBy('name')->pluck('name','id')->all())
                ->required()->searchable()->native(false),
            Forms\Components\DatePicker::make('receipt_date')->label('التاريخ')->required()->default(now()),
            Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
            Forms\Components\Repeater::make('items')->label('الأصناف')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('item_id')->label('الصنف')
                        ->options(fn () => Item::query()->where('station_id', $stationId ?? 0)
                            ->where('is_active', true)->orderBy('name')->pluck('name','id')->all())
                        ->required()->searchable()->native(false),
                    Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()
                        ->required()->minValue(0.0001)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('amount', Decimal::multiply((string) $get('quantity'), (string) $get('unit_cost')));
                        }),
                    Forms\Components\TextInput::make('unit_cost')->label('تكلفة الوحدة')->numeric()
                        ->required()->minValue(0)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('amount', Decimal::multiply((string) $get('quantity'), (string) $get('unit_cost')));
                        }),
                    Forms\Components\TextInput::make('amount')->label('المبلغ')->numeric()->required()->disabled()->dehydrated(),
                ])->columns(4)->minItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('الرقم')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('purchaseOrder.number')->label('أمر الشراء'),
            Tables\Columns\TextColumn::make('warehouse.name')->label('المستودع')->searchable(),
            Tables\Columns\TextColumn::make('receipt_date')->label('التاريخ')->date()->sortable(),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
                'gray'=>'draft','success'=>'posted','danger'=>'voided',
            ])->formatStateUsing(fn (string $state): string => match ($state) {
                'draft'=>'مسودة','posted'=>'مرحل','voided'=>'ملغى',default=>$state,
            }),
        ])->actions([
            Tables\Actions\EditAction::make()
                ->visible(fn (GoodsReceipt $record): bool => $record->status === 'draft'),
            Action::make('post')
                ->label('ترحيل الاستلام')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (GoodsReceipt $record): bool =>
                    (auth()->user()?->can('procurement.manage') ?? false) && $record->status === 'draft')
                ->requiresConfirmation()
                ->action(function (GoodsReceipt $record): void {
                    $stationId = app(StationContext::class)->currentId();
                    abort_unless($stationId && (int) $record->station_id === $stationId, 403);
                    app(\App\Services\Procurement\GoodsReceiptService::class)->post((int) $record->id, auth()->user());
                    Notification::make()->success()->title('تم ترحيل الاستلام')
                        ->body('تم تحديث المخزون وحالة أمر الشراء.')->send();
                }),
        ])->defaultSort('receipt_date','desc');
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && $record->status === 'draft';
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListGoodsReceipts::route('/'),
            'create'=>Pages\CreateGoodsReceipt::route('/create'),
            'edit'=>Pages\EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
