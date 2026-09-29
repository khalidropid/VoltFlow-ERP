<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegacyIdMappingResource\Pages;
use App\Models\LegacyIdMapping;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LegacyIdMappingResource extends Resource
{
    protected static ?string $model = LegacyIdMapping::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'الحوكمة والتكامل';

    public static function getNavigationLabel(): string { return 'ربط السجلات القديمة'; }
    public static function getModelLabel(): string { return 'ربط سجل'; }
    public static function getPluralModelLabel(): string { return 'روابط السجلات القديمة'; }

    public static function canViewAny(): bool { return auth()->user()?->can('integration.view') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('source.code')->label('المصدر')->searchable(),
            Tables\Columns\TextColumn::make('legacy_table')->label('الجدول القديم')->searchable(),
            Tables\Columns\TextColumn::make('legacy_id')->label('المعرّف القديم')->searchable(),
            Tables\Columns\TextColumn::make('entity_type')->label('الكيان الحالي')->searchable(),
            Tables\Columns\TextColumn::make('entity_id')->label('المعرّف الحالي'),
            Tables\Columns\TextColumn::make('created_at')->label('تاريخ الربط')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLegacyIdMappings::route('/')];
    }
}
