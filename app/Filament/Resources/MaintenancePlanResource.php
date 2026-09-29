<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\MaintenancePlanResource\Pages;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenancePlanResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = MaintenancePlan::class;
    protected static string $viewPermission = 'maintenance.view';
    protected static string $managePermission = 'maintenance.manage';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'الصيانة والوقود';
    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string { return 'خطط الصيانة'; }
    public static function getModelLabel(): string { return 'خطة صيانة'; }
    public static function getPluralModelLabel(): string { return 'خطط الصيانة'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();

        return $form->schema([
            Forms\Components\Select::make('asset_id')
                ->label('الأصل')
                ->options(fn () => Asset::query()->where('station_id',$stationId ?? 0)->orderBy('name')->pluck('name','id')->all())
                ->required()->searchable()->native(false),
            Forms\Components\TextInput::make('name')->label('اسم الخطة')->required()->maxLength(255),
            Forms\Components\TextInput::make('interval_days')->label('كل كم يوم')->numeric()->integer(),
            Forms\Components\TextInput::make('interval_hours')->label('كل كم ساعة تشغيل')->numeric(),
            Forms\Components\DatePicker::make('next_due_on')->label('الاستحقاق القادم'),
            Forms\Components\Toggle::make('is_active')->label('نشطة')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('asset.name')->label('الأصل')->searchable(),
            Tables\Columns\TextColumn::make('name')->label('الخطة')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('interval_days')->label('الأيام'),
            Tables\Columns\TextColumn::make('interval_hours')->label('الساعات')->numeric(decimalPlaces: 4),
            Tables\Columns\TextColumn::make('next_due_on')->label('الاستحقاق')->date()->sortable(),
            Tables\Columns\IconColumn::make('is_active')->label('نشطة')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListMaintenancePlans::route('/'),
            'create'=>Pages\CreateMaintenancePlan::route('/create'),
            'edit'=>Pages\EditMaintenancePlan::route('/{record}/edit'),
        ];
    }
}
