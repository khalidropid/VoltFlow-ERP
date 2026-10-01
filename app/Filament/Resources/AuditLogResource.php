<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\StationContext;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'الحوكمة والتكامل';

    public static function getNavigationLabel(): string { return 'سجل التدقيق'; }
    public static function getModelLabel(): string { return 'سجل تدقيق'; }
    public static function getPluralModelLabel(): string { return 'سجلات التدقيق'; }

    public static function canViewAny(): bool
    {
        return self::currentUser()?->can('audit.view') ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        $stationId = app(StationContext::class)->currentId();

        return parent::getEloquentQuery()->when(
            $stationId,
            fn (Builder $query) => $query->where('station_id', $stationId),
            fn (Builder $query) => $query->whereRaw('1 = 0'),
        );
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('created_at')->label('الوقت')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('event')->label('الحدث')->badge(),
            Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->searchable(),
            Tables\Columns\TextColumn::make('auditable_type')->label('نوع السجل')->toggleable(),
            Tables\Columns\TextColumn::make('auditable_id')->label('المعرّف')->toggleable(),
            Tables\Columns\TextColumn::make('request_id')->label('Request ID')->toggleable(),
            Tables\Columns\TextColumn::make('ip_address')->label('IP')->toggleable(),
        ])->defaultSort('created_at', 'desc');
    }

    private static function currentUser(): ?User
    {
        $user = request()->user();

        return $user instanceof User ? $user : null;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
