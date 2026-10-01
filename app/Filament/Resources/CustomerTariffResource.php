<?php
namespace App\Filament\Resources;

use App\Filament\Resources\CustomerTariffResource\Pages;
use App\Models\CustomerTariff;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerTariffResource extends Resource
{
    protected static ?string $model = CustomerTariff::class;
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function getNavigationLabel(): string { return 'تعرفات العملاء'; }
    public static function getModelLabel(): string { return 'تعرفة عميل'; }
    public static function getPluralModelLabel(): string { return 'تعرفات العملاء'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')->label('العميل')->relationship('customer','name')->required()->searchable(),
            Forms\Components\Select::make('tariff_id')->label('التعرفة')->relationship('tariff','name')->required()->searchable(),
            Forms\Components\DatePicker::make('effective_from')->label('سارية من'),
            Forms\Components\DatePicker::make('effective_to')->label('سارية إلى'),
            Forms\Components\Toggle::make('is_active')->label('نشطة')->default(true)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('customer_id')->label('العميل')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tariff_id')->label('التعرفة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('effective_from')->label('سارية من')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('effective_to')->label('سارية إلى')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('is_active')->label('نشطة')->searchable()->sortable()
        ])->actions([Tables\Actions\EditAction::make()])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();
        return $stationId ? $query->where($query->getModel()->getTable().'.station_id', $stationId) : $query->whereRaw('1=0');
    }

    public static function canViewAny(): bool { return auth()->user()?->can('billing.view') ?? false; }
    public static function canCreate(): bool { return (auth()->user()?->can('billing.manage') ?? false) && app(StationContext::class)->currentId() !== null; }
    public static function canEdit($record): bool { return auth()->user()?->can('billing.manage') ?? false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerTariffs::route('/'),
            'create' => Pages\CreateCustomerTariff::route('/create'),
            'edit' => Pages\EditCustomerTariff::route('/{record}/edit'),
        ];
    }
}