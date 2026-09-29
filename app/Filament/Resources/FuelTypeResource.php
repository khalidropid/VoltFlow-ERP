<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\FuelTypeResource\Pages;
use App\Models\FuelType;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FuelTypeResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = FuelType::class;
    protected static string $viewPermission = 'fuel.view';
    protected static string $managePermission = 'fuel.manage';
    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationGroup = 'الصيانة والوقود';
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string { return 'أنواع الوقود'; }
    public static function getModelLabel(): string { return 'نوع وقود'; }
    public static function getPluralModelLabel(): string { return 'أنواع الوقود'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('الرمز')->required()->maxLength(50),
            Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            Forms\Components\TextInput::make('unit')->label('الوحدة')->required()->default('liter')->maxLength(20),
            Forms\Components\TextInput::make('density')->label('الكثافة')->numeric(),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('الوقود')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('unit')->label('الوحدة'),
            Tables\Columns\TextColumn::make('density')->label('الكثافة')->numeric(decimalPlaces: 4),
            Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();

        return $stationId
            ? $query->where(function ($q) use ($stationId) {
                $q->where('fuel_types.station_id', $stationId)->orWhereNull('fuel_types.station_id');
            })
            : $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListFuelTypes::route('/'),
            'create'=>Pages\CreateFuelType::route('/create'),
            'edit'=>Pages\EditFuelType::route('/{record}/edit'),
        ];
    }
}
