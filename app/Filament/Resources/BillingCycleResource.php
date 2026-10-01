<?php
namespace App\Filament\Resources;

use App\Filament\Resources\BillingCycleResource\Pages;
use App\Models\BillingCycle;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillingCycleResource extends Resource
{
    protected static ?string $model = BillingCycle::class;
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function getNavigationLabel(): string { return 'دورات الفوترة'; }
    public static function getModelLabel(): string { return 'دورة فوترة'; }
    public static function getPluralModelLabel(): string { return 'دورات الفوترة'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('billing_period_id')->label('الفترة')->relationship('period','name')->required()->searchable(),
            Forms\Components\TextInput::make('code')->label('رمز الدورة')->required()->maxLength(255),
            Forms\Components\TextInput::make('name')->label('اسم الدورة')->required()->maxLength(255),
            Forms\Components\DatePicker::make('reading_from')->label('قراءة من'),
            Forms\Components\DatePicker::make('reading_to')->label('قراءة إلى'),
            Forms\Components\DatePicker::make('billing_date')->label('تاريخ الفوترة'),
            Forms\Components\Select::make('status')->label('الحالة')->options(['draft'=>'مسودة','processing'=>'قيد التنفيذ','completed'=>'مكتملة','cancelled'=>'ملغاة'])->required()
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('billing_period_id')->label('الفترة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('رمز الدورة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('اسم الدورة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('reading_from')->label('قراءة من')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('reading_to')->label('قراءة إلى')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('billing_date')->label('تاريخ الفوترة')->searchable()->sortable(),
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
    public static function canCreate(): bool { return (auth()->user()?->can('billing.manage') ?? false) && app(StationContext::class)->currentId() !== null; }
    public static function canEdit($record): bool { return auth()->user()?->can('billing.manage') ?? false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingCycles::route('/'),
            'create' => Pages\CreateBillingCycle::route('/create'),
            'edit' => Pages\EditBillingCycle::route('/{record}/edit'),
        ];
    }
}