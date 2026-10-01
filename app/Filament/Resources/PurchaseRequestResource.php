<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\PurchaseRequestResource\Pages;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseRequestResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = PurchaseRequest::class;
    protected static string $viewPermission = 'procurement.view';
    protected static string $managePermission = 'procurement.manage';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';
    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string { return 'طلبات الشراء'; }
    public static function getModelLabel(): string { return 'طلب شراء'; }
    public static function getPluralModelLabel(): string { return 'طلبات الشراء'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();

        return $form->schema([
            Forms\Components\TextInput::make('number')->label('رقم الطلب')->required()->maxLength(50),
            Forms\Components\DatePicker::make('request_date')->label('التاريخ')->required()->default(now()),
            Forms\Components\Select::make('status')->label('الحالة')->options([
                'draft'=>'مسودة','submitted'=>'مقدم','approved'=>'معتمد','rejected'=>'مرفوض','cancelled'=>'ملغى',
            ])->required()->default('draft')->native(false),
            Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
            Forms\Components\Repeater::make('items')->label('الأصناف المطلوبة')->relationship()
                ->schema([
                    Forms\Components\Select::make('item_id')
                        ->label('الصنف')
                        ->options(fn()=>Item::query()->where('station_id',$stationId??0)->orderBy('name')->pluck('name','id')->all())
                        ->required()->searchable()->native(false),
                    Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()->required()->minValue(0.0001),
                    Forms\Components\TextInput::make('description')->label('الوصف')->maxLength(500),
                ])->columns(3)->minItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('الرقم')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('request_date')->label('التاريخ')->date()->sortable(),
            Tables\Columns\TextColumn::make('requestedBy.name')->label('طالب الشراء'),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
                'gray'=>'draft','warning'=>'submitted','success'=>'approved','danger'=>'rejected','info'=>'cancelled',
            ]),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListPurchaseRequests::route('/'),
            'create'=>Pages\CreatePurchaseRequest::route('/create'),
            'edit'=>Pages\EditPurchaseRequest::route('/{record}/edit'),
        ];
    }
}
