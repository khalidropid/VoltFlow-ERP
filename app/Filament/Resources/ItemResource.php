<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = Item::class;
    protected static string $viewPermission = 'inventory.view';
    protected static string $managePermission = 'inventory.manage';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string { return 'الأصناف'; }
    public static function getModelLabel(): string { return 'صنف'; }
    public static function getPluralModelLabel(): string { return 'الأصناف'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();

        return $form->schema([
            Forms\Components\TextInput::make('code')->label('رمز الصنف')->required()->maxLength(80),
            Forms\Components\TextInput::make('name')->label('اسم الصنف')->required()->maxLength(255),
            Forms\Components\Select::make('item_category_id')
                ->label('التصنيف')->options(fn () => ItemCategory::query()
                    ->where('station_id', $stationId)
                    ->orderBy('name')->pluck('name','id')->all())->searchable()->native(false),
            Forms\Components\Select::make('unit_of_measure_id')
                ->label('وحدة القياس')->options(fn () => UnitOfMeasure::query()->orderBy('name')->pluck('name','id')->all())
                ->required()->searchable()->native(false),
            Forms\Components\Select::make('item_type')
                ->label('نوع الصنف')->options([
                    'stock'=>'مخزني','service'=>'خدمي','asset'=>'أصل','consumable'=>'استهلاكي',
                ])->required()->default('stock')->native(false),
            Forms\Components\TextInput::make('standard_cost')->label('التكلفة المعيارية')->numeric()->default(0)->required(),
            Forms\Components\TextInput::make('reorder_level')->label('حد إعادة الطلب')->numeric()->default(0)->required(),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('الصنف')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category.name')->label('التصنيف'),
            Tables\Columns\TextColumn::make('unitOfMeasure.symbol')->label('الوحدة'),
            Tables\Columns\TextColumn::make('standard_cost')->label('التكلفة')->numeric(decimalPlaces: 4),
            Tables\Columns\TextColumn::make('reorder_level')->label('حد الطلب')->numeric(decimalPlaces: 4),
            Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListItems::route('/'),
            'create'=>Pages\CreateItem::route('/create'),
            'edit'=>Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
