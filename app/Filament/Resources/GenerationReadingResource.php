<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\GenerationReadingResource\Pages;
use App\Models\GenerationReading;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class GenerationReadingResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model = GenerationReading::class;
    protected static string $viewPermission='generation.view';
    protected static string $managePermission='generation.manage';
    protected static ?string $navigationIcon='heroicon-o-chart-bar';
    protected static ?string $navigationGroup='التشغيل';
    protected static ?int $navigationSort=40;
    public static function getNavigationLabel(): string { return 'قراءات التوليد'; }
    public static function getModelLabel(): string { return 'قراءة توليد'; }
    public static function getPluralModelLabel(): string { return 'قراءات التوليد'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('generator.name')->label('المولد')->searchable(),
        Tables\Columns\TextColumn::make('reading_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
        Tables\Columns\TextColumn::make('energy_kwh')->label('الطاقة kWh')->numeric(decimalPlaces:4)->sortable(),
        Tables\Columns\TextColumn::make('active_power_kw')->label('القدرة الفعالة')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('reactive_power_kvar')->label('القدرة غير الفعالة')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('source')->label('المصدر'),
    ])->defaultSort('reading_at','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListGenerationReadings::route('/')]; }
}
