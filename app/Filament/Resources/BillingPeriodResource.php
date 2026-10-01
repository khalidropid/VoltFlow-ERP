<?php
namespace App\Filament\Resources;

use App\Filament\Resources\BillingPeriodResource\Pages;
use App\Models\BillingPeriod;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillingPeriodResource extends Resource
{
    protected static ?string $model = BillingPeriod::class;
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function getNavigationLabel(): string { return 'فترات الفوترة'; }
    public static function getModelLabel(): string { return 'فترة فوترة'; }
    public static function getPluralModelLabel(): string { return 'فترات الفوترة'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('رمز الفترة')->required()->maxLength(255),
            Forms\Components\TextInput::make('name')->label('اسم الفترة')->required()->maxLength(255),
            Forms\Components\DatePicker::make('starts_on')->label('تبدأ'),
            Forms\Components\DatePicker::make('ends_on')->label('تنتهي'),
            Forms\Components\Select::make('status')->label('الحالة')->options(['open'=>'مفتوحة','closed'=>'مغلقة'])->required()
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('code')->label('رمز الفترة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('اسم الفترة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('starts_on')->label('تبدأ')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ends_on')->label('تنتهي')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->searchable()->sortable()
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
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingPeriods::route('/'),
            'create' => Pages\CreateBillingPeriod::route('/create'),
            'edit' => Pages\EditBillingPeriod::route('/{record}/edit'),
        ];
    }
}