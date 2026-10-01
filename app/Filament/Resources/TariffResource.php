<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TariffResource\Pages;
use App\Models\Tariff;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TariffResource extends Resource
{
    protected static ?string $model = Tariff::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string { return 'التعرفة'; }
    public static function getModelLabel(): string { return 'تعرفة'; }
    public static function getPluralModelLabel(): string { return 'التعرفة'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات التعرفة')
                ->schema([
                    Forms\Components\TextInput::make('code')->label('رمز التعرفة')->required()->maxLength(50),
                    Forms\Components\TextInput::make('name')->label('اسم التعرفة')->required()->maxLength(255),
                    Forms\Components\DatePicker::make('effective_from')->label('سارية من')->required(),
                    Forms\Components\DatePicker::make('effective_to')->label('سارية إلى'),
                    Forms\Components\Toggle::make('is_active')->label('نشطة')->default(true),
                ])->columns(2),

            Forms\Components\Section::make('شرائح التعرفة')
                ->schema([
                    Forms\Components\Placeholder::make('slabs_info')
                        ->label('الشرائح')
                        ->content('تُدار شرائح التعرفة في مرحلة فوترة التعرفة التفصيلية، مع الحفاظ على ترتيب الشرائح وقيمها العشرية.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('التعرفة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('effective_from')->label('من')->date(),
                Tables\Columns\TextColumn::make('effective_to')->label('إلى')->date(),
                Tables\Columns\IconColumn::make('is_active')->label('نشطة')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();

        return $stationId
            ? $query->where('tariffs.station_id', $stationId)
            : $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('billing.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return (auth()->user()?->can('billing.manage') ?? false)
            && app(StationContext::class)->currentId() !== null;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('billing.manage') ?? false;
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTariffs::route('/'),
            'create' => Pages\CreateTariff::route('/create'),
            'edit' => Pages\EditTariff::route('/{record}/edit'),
        ];
    }
}
