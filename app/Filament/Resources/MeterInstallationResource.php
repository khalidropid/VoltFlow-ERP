<?php
namespace App\Filament\Resources;

use App\Filament\Resources\MeterInstallationResource\Pages;
use App\Models\MeterInstallation;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeterInstallationResource extends Resource
{
    protected static ?string $model = MeterInstallation::class;
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function getNavigationLabel(): string { return 'تركيبات العدادات'; }
    public static function getModelLabel(): string { return 'تركيب عداد'; }
    public static function getPluralModelLabel(): string { return 'تركيبات العدادات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('meter_id')->label('العداد')->relationship('meter','serial_number')->required()->searchable(),
            Forms\Components\Select::make('customer_id')->label('العميل')->relationship('customer','name')->required()->searchable(),
            Forms\Components\DatePicker::make('installed_on')->label('تاريخ التركيب'),
            Forms\Components\DatePicker::make('removed_on')->label('تاريخ الإزالة'),
            Forms\Components\TextInput::make('initial_reading')->label('القراءة الابتدائية')->numeric()->required(),
            Forms\Components\Textarea::make('notes')->label('ملاحظات')->maxLength(1000)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('meter_id')->label('العداد')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_id')->label('العميل')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('installed_on')->label('تاريخ التركيب')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('removed_on')->label('تاريخ الإزالة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('initial_reading')->label('القراءة الابتدائية')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('notes')->label('ملاحظات')->searchable()->sortable()
        ])->actions([Tables\Actions\EditAction::make()])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();
        return $stationId ? $query->where($query->getModel()->getTable().'.station_id', $stationId) : $query->whereRaw('1=0');
    }

    public static function canViewAny(): bool { return auth()->user()?->can('customers.view') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeterInstallations::route('/'),
            'create' => Pages\CreateMeterInstallation::route('/create'),
            'edit' => Pages\EditMeterInstallation::route('/{record}/edit'),
        ];
    }
}