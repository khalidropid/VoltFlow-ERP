<?php
namespace App\Filament\Resources;
use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\MaintenanceWorkOrderResource\Pages;
use App\Models\MaintenanceWorkOrder;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class MaintenanceWorkOrderResource extends Resource
{
    use StationScopedResource;
    protected static ?string $model=MaintenanceWorkOrder::class;
    protected static string $viewPermission='maintenance.view';
    protected static string $managePermission='maintenance.manage';
    protected static ?string $navigationIcon='heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup='الصيانة والوقود';
    protected static ?int $navigationSort=60;
    public static function getNavigationLabel(): string { return 'أوامر الصيانة'; }
    public static function getModelLabel(): string { return 'أمر صيانة'; }
    public static function getPluralModelLabel(): string { return 'أوامر الصيانة'; }
    public static function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('number')->label('الرقم')->searchable()->sortable(),
        Tables\Columns\TextColumn::make('asset.name')->label('الأصل')->searchable(),
        Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable(),
        Tables\Columns\TextColumn::make('type')->label('النوع'),
        Tables\Columns\TextColumn::make('priority')->label('الأولوية'),
        Tables\Columns\BadgeColumn::make('status')->label('الحالة')
            ->colors(['gray'=>'draft','warning'=>'open','info'=>'in_progress','success'=>'completed','danger'=>'cancelled']),
        Tables\Columns\TextColumn::make('opened_on')->label('فتح')->date(),
        Tables\Columns\TextColumn::make('completed_on')->label('إكمال')->date(),
    ])->defaultSort('opened_on','desc'); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function getPages(): array { return ['index'=>Pages\ListMaintenanceWorkOrders::route('/')]; }
}
