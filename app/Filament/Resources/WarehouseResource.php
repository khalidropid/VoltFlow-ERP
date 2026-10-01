<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = Warehouse::class;
    protected static string $viewPermission = 'inventory.view';
    protected static string $managePermission = 'inventory.manage';
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string { return 'المستودعات'; }
    public static function getModelLabel(): string { return 'مستودع'; }
    public static function getPluralModelLabel(): string { return 'المستودعات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('الرمز')->required()->maxLength(50),
            Forms\Components\TextInput::make('name')->label('اسم المستودع')->required()->maxLength(255),
            Forms\Components\Select::make('type')->label('النوع')->options([
                'main'=>'رئيسي','fuel'=>'وقود','spares'=>'قطع غيار','other'=>'أخرى',
            ])->required()->default('main')->native(false),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('المستودع')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('type')->label('النوع'),
            Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListWarehouses::route('/'),
            'create'=>Pages\CreateWarehouse::route('/create'),
            'edit'=>Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
