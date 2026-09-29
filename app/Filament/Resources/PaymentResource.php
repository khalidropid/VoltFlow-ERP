<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\Collections\PaymentReversalService;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string { return 'التحصيلات'; }
    public static function getModelLabel(): string { return 'تحصيل'; }
    public static function getPluralModelLabel(): string { return 'التحصيلات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('payment_read_only')
                ->label('سجل محاسبي')
                ->content('التحصيلات المرحّلة لا تُعدل مباشرة. الإلغاء يتم عبر إجراء عكسي موثّق ومحكوم بالصلاحيات.'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('receipt_number')->label('رقم الإيصال'),
            TextEntry::make('customer.name')->label('العميل'),
            TextEntry::make('invoice.number')->label('الفاتورة'),
            TextEntry::make('collector.name')->label('المحصل'),
            TextEntry::make('cashAccount.name')->label('النقدية / البنك'),
            TextEntry::make('paid_at')->label('وقت التحصيل')->dateTime(),
            TextEntry::make('amount')->label('المبلغ'),
            TextEntry::make('method')->label('الطريقة')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'cash' => 'نقدًا',
                    'bank' => 'بنك',
                    'transfer' => 'تحويل',
                    'other' => 'أخرى',
                    default => $state,
                }),
            TextEntry::make('status')->label('الحالة')
                ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'مرحل' : 'ملغى'),
            TextEntry::make('journal_entry_id')->label('قيد التحصيل')->placeholder('غير متوفر'),
            TextEntry::make('reversal_journal_entry_id')->label('قيد العكس')->placeholder('غير موجود'),
            TextEntry::make('voided_at')->label('تاريخ الإلغاء')->dateTime()->placeholder('غير ملغى'),
            TextEntry::make('void_reason')->label('سبب الإلغاء')->placeholder('—'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')->label('الإيصال')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('العميل')->searchable(),
                Tables\Columns\TextColumn::make('invoice.number')->label('الفاتورة')->searchable(),
                Tables\Columns\TextColumn::make('collector.name')->label('المحصل')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->numeric(decimalPlaces: 4)->sortable(),
                Tables\Columns\TextColumn::make('paid_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors(['success' => 'posted', 'danger' => 'voided'])
                    ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'مرحل' : 'ملغى'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('void')
                    ->label('إلغاء التحصيل')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool =>
                        (auth()->user()?->can('collections.void') ?? false) && $record->status === 'posted'
                    )
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('سبب الإلغاء')
                            ->required()
                            ->minLength(5)
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Payment $record, array $data): void {
                        $stationId = app(StationContext::class)->currentId();

                        abort_unless($stationId && (int) $record->station_id === $stationId, 403);

                        app(PaymentReversalService::class)->void(
                            (int) $record->id,
                            $stationId,
                            (int) auth()->id(),
                            now()->format('Y-m-d H:i:s'),
                            (string) $data['reason'],
                        );

                        Notification::make()
                            ->success()
                            ->title('تم إلغاء التحصيل')
                            ->body('تم إنشاء قيد العكس وتحديث رصيد عهدة المحصل.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();

        return $stationId
            ? $query->where('payments.station_id', $stationId)
            : $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('collections.view') ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
