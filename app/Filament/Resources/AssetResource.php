<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = Asset::class;
    protected static string $viewPermission = 'maintenance.view';
    protected static string $managePermission = 'maintenance.manage';
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'الصيانة والوقود';
    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string { return 'الأصول والمعدات'; }
    public static function getModelLabel(): string { return 'أصل'; }
    public static function getPluralModelLabel(): string { return 'الأصول والمعدات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('رمز الأصل')->required()->maxLength(80),
            Forms\Components\TextInput::make('name')->label('اسم الأصل')->required()->maxLength(255),
            Forms\Components\Select::make('asset_category_id')
                ->label('التصنيف')
                ->options(fn () => AssetCategory::query()
                    ->where(function ($q) {
                        $stationId = app(StationContext::class)->currentId();
                        $q->where('station_id', $stationId)->orWhereNull('station_id');
                    })
                    ->orderBy('name')->pluck('name','id')->all())
                ->searchable()->native(false),
            Forms\Components\TextInput::make('serial_number')->label('الرقم التسلسلي')->maxLength(255),
            Forms\Components\DatePicker::make('acquired_on')->label('تاريخ الاقتناء'),
            Forms\Components\TextInput::make('purchase_cost')->label('تكلفة الاقتناء')->numeric()->default(0)->required(),
            Forms\Components\TextInput::make('residual_value')->label('القيمة التخريدية')->numeric()->default(0)->required(),
            Forms\Components\TextInput::make('useful_life_months')->label('العمر الإنتاجي بالأشهر')->numeric()->integer(),
            Forms\Components\DatePicker::make('depreciation_start_on')->label('بدء الإهلاك'),
            Forms\Components\Select::make('status')->label('الحالة')->options([
                'active'=>'نشط','maintenance'=>'صيانة','disposed'=>'مستبعد','retired'=>'متقاعد',
            ])->required()->default('active')->native(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('الأصل')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category.name')->label('التصنيف'),
            Tables\Columns\TextColumn::make('purchase_cost')->label('التكلفة')->numeric(decimalPlaces: 4),
            Tables\Columns\TextColumn::make('useful_life_months')->label('العمر'),
            Tables\Columns\BadgeColumn::make('status')
                ->label('الحالة')
                ->colors(['success'=>'active','warning'=>'maintenance','danger'=>'disposed','gray'=>'retired'])
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'active'=>'نشط','maintenance'=>'صيانة','disposed'=>'مستبعد','retired'=>'متقاعد',default=>$state
                }),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListAssets::route('/'),
            'create'=>Pages\CreateAsset::route('/create'),
            'edit'=>Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
