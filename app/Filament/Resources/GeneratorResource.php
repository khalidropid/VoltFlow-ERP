<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\GeneratorResource\Pages;
use App\Models\Generator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GeneratorResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = Generator::class;
    protected static string $viewPermission = 'generation.view';
    protected static string $managePermission = 'generation.manage';
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'التشغيل';
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string { return 'المولدات'; }
    public static function getModelLabel(): string { return 'مولد'; }
    public static function getPluralModelLabel(): string { return 'المولدات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات المولد')->schema([
                Forms\Components\TextInput::make('code')->label('الرمز')->required()->maxLength(50),
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\TextInput::make('manufacturer')->label('الشركة المصنعة')->maxLength(255),
                Forms\Components\TextInput::make('model')->label('الموديل')->maxLength(255),
                Forms\Components\TextInput::make('serial_number')->label('الرقم التسلسلي')->maxLength(255),
                Forms\Components\TextInput::make('capacity_kw')->label('القدرة kW')->numeric()->required()->default(0),
                Forms\Components\TextInput::make('rated_voltage')->label('الجهد المقنن')->numeric(),
                Forms\Components\TextInput::make('fuel_type')->label('نوع الوقود')->maxLength(50),
                Forms\Components\Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'نشط',
                        'inactive' => 'متوقف',
                        'maintenance' => 'صيانة',
                        'retired' => 'متقاعد',
                    ])->required()->default('active')->native(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('المولد')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('capacity_kw')->label('القدرة')->numeric(decimalPlaces: 4),
            Tables\Columns\TextColumn::make('fuel_type')->label('الوقود'),
            Tables\Columns\BadgeColumn::make('status')
                ->label('الحالة')
                ->colors(['success'=>'active','warning'=>'maintenance','danger'=>'retired','gray'=>'inactive'])
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'active'=>'نشط','inactive'=>'متوقف','maintenance'=>'صيانة','retired'=>'متقاعد',default=>$state
                }),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGenerators::route('/'),
            'create' => Pages\CreateGenerator::route('/create'),
            'edit' => Pages\EditGenerator::route('/{record}/edit'),
        ];
    }
}
