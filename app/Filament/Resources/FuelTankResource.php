<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\FuelTankResource\Pages;
use App\Models\FuelTank;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FuelTankResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = FuelTank::class;
    protected static string $viewPermission = 'fuel.view';
    protected static string $managePermission = 'fuel.manage';
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationGroup = 'الصيانة والوقود';
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string { return 'خزانات الوقود'; }
    public static function getModelLabel(): string { return 'خزان وقود'; }
    public static function getPluralModelLabel(): string { return 'خزانات الوقود'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('الرمز')->required()->maxLength(50),
            Forms\Components\TextInput::make('name')->label('اسم الخزان')->required()->maxLength(255),
            Forms\Components\TextInput::make('capacity')->label('السعة')->numeric()->required()->default(0),
            Forms\Components\Placeholder::make('current_quantity_info')->label('الرصيد الحالي')->content(fn (?FuelTank $record): string => $record?->current_quantity ?? '0.0000'),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('الخزان')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('capacity')->label('السعة')->numeric(decimalPlaces: 4),
            Tables\Columns\TextColumn::make('current_quantity')->label('الرصيد الحالي')->numeric(decimalPlaces: 4),
            Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListFuelTanks::route('/'),
            'create'=>Pages\CreateFuelTank::route('/create'),
            'edit'=>Pages\EditFuelTank::route('/{record}/edit'),
        ];
    }
}
