<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\FeederReadingResource\Pages;
use App\Models\FeederReading;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class FeederReadingResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=FeederReading::class;
    protected static string $viewPermission='generation.view';
    protected static string $managePermission='generation.manage';
    protected static ?string $navigationIcon='heroicon-o-chart-pie';
    protected static ?string $navigationGroup='التشغيل';
    protected static ?int $navigationSort=60;
    public static function getNavigationLabel(): string { return 'قراءات المغذيات'; }
    public static function getModelLabel(): string { return 'قراءة مغذي'; }
    public static function getPluralModelLabel(): string { return 'قراءات المغذيات'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('feeder.name')->label('المغذي')->searchable(),
        Tables\Columns\TextColumn::make('reading_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
        Tables\Columns\TextColumn::make('energy_kwh')->label('الطاقة kWh')->numeric(decimalPlaces:4)->sortable(),
        Tables\Columns\TextColumn::make('current_amp')->label('التيار')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('voltage')->label('الجهد')->numeric(decimalPlaces:4),
    ])->defaultSort('reading_at','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListFeederReadings::route('/')]; }
}
