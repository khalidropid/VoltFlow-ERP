<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrationBatchResource\Pages;
use App\Models\IntegrationBatch;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IntegrationBatchResource extends Resource
{
    protected static ?string $model = IntegrationBatch::class;
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'الحوكمة والتكامل';

    public static function getNavigationLabel(): string { return 'دفعات التكامل'; }
    public static function getModelLabel(): string { return 'دفعة تكامل'; }
    public static function getPluralModelLabel(): string { return 'دفعات التكامل'; }

    public static function canViewAny(): bool { return auth()->user()?->can('integration.view') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('started_at')->label('البدء')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('source.code')->label('المصدر')->searchable(),
            Tables\Columns\TextColumn::make('batch_uuid')->label('Batch UUID')->copyable(),
            Tables\Columns\TextColumn::make('external_batch_id')->label('المعرّف الخارجي')->searchable(),
            Tables\Columns\TextColumn::make('status')->label('الحالة')->badge(),
            Tables\Columns\TextColumn::make('received_count')->label('المستلمة'),
            Tables\Columns\TextColumn::make('processed_count')->label('المعالجة'),
            Tables\Columns\TextColumn::make('failed_count')->label('الفاشلة'),
        ])->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListIntegrationBatches::route('/')];
    }
}
