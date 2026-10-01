<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserDeviceResource\Pages;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use App\Services\Integration\IntegrationService;
use App\Support\StationContext;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserDeviceResource extends Resource
{
    protected static ?string $model = UserDevice::class;
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationGroup = 'الحوكمة والتكامل';

    public static function getNavigationLabel(): string { return 'أجهزة المستخدمين'; }
    public static function getModelLabel(): string { return 'جهاز مستخدم'; }
    public static function getPluralModelLabel(): string { return 'أجهزة المستخدمين'; }

    public static function canViewAny(): bool { return self::currentUser()?->can('integration.view') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery()
    {
        $stationId = app(StationContext::class)->currentId();

        return parent::getEloquentQuery()
            ->with('user')
            ->when(
                $stationId,
                fn ($query) => $query->whereHas('user.stations', fn ($q) => $q->whereKey($stationId)),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->searchable(),
            Tables\Columns\TextColumn::make('user.email')->label('البريد')->searchable(),
            Tables\Columns\TextColumn::make('device_id')->label('Device ID')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('platform')->label('المنصة'),
            Tables\Columns\TextColumn::make('app_version')->label('إصدار التطبيق'),
            Tables\Columns\TextColumn::make('is_approved')->label('الحالة')
                ->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? 'معتمد' : 'بانتظار الاعتماد')
                ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
            Tables\Columns\TextColumn::make('last_seen_at')->label('آخر ظهور')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\Action::make('approve')
                ->label('اعتماد الجهاز')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (UserDevice $record): bool => ! $record->is_approved && (self::currentUser()?->can('integration.manage') ?? false))
                ->requiresConfirmation()
                ->action(function (UserDevice $record): void {
                    $device = app(IntegrationService::class)->approveDevice($record);
                    app(AuditLogger::class)->record(
                        event: 'device.approved',
                        auditable: $device,
                        oldValues: ['is_approved' => false],
                        newValues: ['is_approved' => true],
                        stationId: app(StationContext::class)->currentId(),
                        request: request(),
                    );
                }),
        ]);
    }

    private static function currentUser(): ?User
    {
        $user = request()->user();

        return $user instanceof User ? $user : null;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUserDevices::route('/')];
    }
}
