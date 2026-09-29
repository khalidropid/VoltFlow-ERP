<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\FeederResource\Pages;
use App\Models\Feeder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FeederResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = Feeder::class;
    protected static string $viewPermission = 'generation.view';
    protected static string $managePermission = 'generation.manage';
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'التشغيل';
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string { return 'المغذيات'; }
    public static function getModelLabel(): string { return 'مغذي'; }
    public static function getPluralModelLabel(): string { return 'المغذيات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('الرمز')->required()->maxLength(50),
            Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            Forms\Components\TextInput::make('capacity_kw')->label('السعة kW')->numeric()->required()->default(0),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options(['active'=>'نشط','inactive'=>'متوقف','maintenance'=>'صيانة'])
                ->required()->default('active')->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('المغذي')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('capacity_kw')->label('السعة')->numeric(decimalPlaces: 4),
            Tables\Columns\BadgeColumn::make('status')
                ->label('الحالة')
                ->colors(['success'=>'active','warning'=>'maintenance','gray'=>'inactive'])
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'active'=>'نشط','inactive'=>'متوقف','maintenance'=>'صيانة',default=>$state
                }),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListFeeders::route('/'),
            'create'=>Pages\CreateFeeder::route('/create'),
            'edit'=>Pages\EditFeeder::route('/{record}/edit'),
        ];
    }
}
