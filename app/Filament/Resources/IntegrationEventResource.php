<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrationEventResource\Pages;
use App\Models\IntegrationEvent;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IntegrationEventResource extends Resource
{
    protected static ?string $model = IntegrationEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'الحوكمة والتكامل';

    public static function getNavigationLabel(): string { return 'أحداث التكامل'; }
    public static function getModelLabel(): string { return 'حدث تكامل'; }
    public static function getPluralModelLabel(): string { return 'أحداث التكامل'; }

    public static function canViewAny(): bool { return self::currentUser()?->can('integration.view') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('received_at')->label('الاستلام')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('source.code')->label('المصدر')->searchable(),
            Tables\Columns\TextColumn::make('entity_type')->label('الكيان')->searchable(),
            Tables\Columns\TextColumn::make('external_id')->label('المعرّف الخارجي')->searchable(),
            Tables\Columns\TextColumn::make('event_type')->label('نوع الحدث')->searchable(),
            Tables\Columns\TextColumn::make('status')->label('الحالة')->badge(),
            Tables\Columns\TextColumn::make('event_uuid')->label('UUID')->copyable()->toggleable(),
        ])->defaultSort('received_at', 'desc');
    }

    private static function currentUser(): ?User
    {
        $user = request()->user();

        return $user instanceof User ? $user : null;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListIntegrationEvents::route('/')];
    }
}
