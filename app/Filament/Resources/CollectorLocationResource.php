<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CollectorLocationResource\Pages;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Models\CollectorLocation;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CollectorLocationResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = CollectorLocation::class;
    protected static string $viewPermission = 'integration.view';
    protected static string $managePermission = 'integration.manage';
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'الحوكمة والتكامل';

    public static function getNavigationLabel(): string { return 'مواقع المحصلين'; }
    public static function getModelLabel(): string { return 'موقع محصل'; }
    public static function getPluralModelLabel(): string { return 'مواقع المحصلين'; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('recorded_at')->label('وقت التسجيل')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('collector.name')->label('المحصل')->searchable(),
            Tables\Columns\TextColumn::make('latitude')->label('خط العرض'),
            Tables\Columns\TextColumn::make('longitude')->label('خط الطول'),
            Tables\Columns\TextColumn::make('accuracy_meters')->label('الدقة (م)'),
            Tables\Columns\TextColumn::make('device_id')->label('الجهاز')->copyable(),
        ])->defaultSort('recorded_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCollectorLocations::route('/')];
    }
}
