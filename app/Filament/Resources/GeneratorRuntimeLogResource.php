<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\GeneratorRuntimeLogResource\Pages;
use App\Models\GeneratorRuntimeLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class GeneratorRuntimeLogResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=GeneratorRuntimeLog::class;
    protected static string $viewPermission='generation.view';
    protected static string $managePermission='generation.manage';
    protected static ?string $navigationIcon='heroicon-o-clock';
    protected static ?string $navigationGroup='التشغيل';
    protected static ?int $navigationSort=50;
    public static function getNavigationLabel(): string { return 'ساعات تشغيل المولدات'; }
    public static function getModelLabel(): string { return 'سجل تشغيل'; }
    public static function getPluralModelLabel(): string { return 'سجلات تشغيل المولدات'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('generator.name')->label('المولد')->searchable(),
        Tables\Columns\TextColumn::make('started_at')->label('بدء')->dateTime('Y-m-d H:i')->sortable(),
        Tables\Columns\TextColumn::make('stopped_at')->label('توقف')->dateTime('Y-m-d H:i'),
        Tables\Columns\TextColumn::make('hours')->label('الساعات')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('load_percent')->label('الحمل %')->numeric(decimalPlaces:4),
        Tables\Columns\TextColumn::make('energy_kwh')->label('الطاقة kWh')->numeric(decimalPlaces:4),
    ])->defaultSort('started_at','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListGeneratorRuntimeLogs::route('/')]; }
}
