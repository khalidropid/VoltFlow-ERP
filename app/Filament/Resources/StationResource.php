<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StationResource\Pages;
use App\Models\Station;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StationResource extends Resource
{
    protected static ?string $model = Station::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'الإدارة الأساسية';
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string { return 'المحطات'; }
    public static function getModelLabel(): string { return 'محطة'; }
    public static function getPluralModelLabel(): string { return 'المحطات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('رمز المحطة')->required()->maxLength(50),
            Forms\Components\TextInput::make('name')->label('اسم المحطة')->required()->maxLength(255),
            Forms\Components\TextInput::make('name_ar')->label('الاسم بالعربية')->maxLength(255),
            Forms\Components\TextInput::make('timezone')->label('المنطقة الزمنية')->required()->default('Asia/Aden')->maxLength(100),
            Forms\Components\TextInput::make('currency_code')->label('رمز العملة')->required()->default('YER')->maxLength(3),
            Forms\Components\Toggle::make('is_active')->label('نشطة')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('المحطة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('timezone')->label('المنطقة الزمنية'),
                Tables\Columns\TextColumn::make('currency_code')->label('العملة'),
                Tables\Columns\IconColumn::make('is_active')->label('نشطة')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('stations.id', $user->stations()->select('stations.id'));
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('stations.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('stations.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('stations.manage') ?? false;
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStations::route('/'),
            'create' => Pages\CreateStation::route('/create'),
            'edit' => Pages\EditStation::route('/{record}/edit'),
        ];
    }
}
