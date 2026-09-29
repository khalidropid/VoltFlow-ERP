<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\CashAccount;
use App\Models\CollectorAccount;
use App\Models\Invoice;
use App\Services\Collections\PaymentService;
use App\Support\Decimal;
use App\Support\StationContext;
use Carbon\Carbon;
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
use Illuminate\Support\Str;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string { return 'الفواتير'; }
    public static function getModelLabel(): string { return 'فاتورة'; }
    public static function getPluralModelLabel(): string { return 'الفواتير'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('invoice_read_only')
                ->label('إدارة الفاتورة')
                ->content('إنشاء وإصدار الفواتير محاسبيًا سيتم عبر دورة الفوترة المعتمدة. لا يتم إنشاء قيد مباشر من شاشة CRUD.'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('number')->label('رقم الفاتورة'),
            TextEntry::make('customer.name')->label('العميل'),
            TextEntry::make('invoice_date')->label('تاريخ الفاتورة')->date(),
            TextEntry::make('due_date')->label('تاريخ الاستحقاق')->date(),
            TextEntry::make('previous_reading')->label('القراءة السابقة'),
            TextEntry::make('current_reading')->label('القراءة الحالية'),
            TextEntry::make('consumption')->label('الاستهلاك'),
            TextEntry::make('subtotal')->label('الإجمالي قبل الخصم والضريبة'),
            TextEntry::make('discount')->label('الخصم'),
            TextEntry::make('tax')->label('الضريبة'),
            TextEntry::make('total')->label('الإجمالي'),
            TextEntry::make('paid_amount')->label('المدفوع'),
            TextEntry::make('status')->label('الحالة')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'draft' => 'مسودة',
                    'issued' => 'مصدرة',
                    'partially_paid' => 'مدفوعة جزئيًا',
                    'paid' => 'مدفوعة',
                    'void' => 'ملغاة',
                    default => $state,
                }),
            TextEntry::make('journal_entry_id')->label('قيد الأستاذ العام')->placeholder('غير مرحل'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('رقم الفاتورة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('العميل')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->label('التاريخ')->date()->sortable(),
                Tables\Columns\TextColumn::make('total')->label('الإجمالي')->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('paid_amount')->label('المدفوع')->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('outstanding')
                    ->label('المتبقي')
                    ->state(fn (Invoice $record): string => Decimal::sub((string) $record->total, (string) $record->paid_amount))
                    ->numeric(decimalPlaces: 4),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'issued',
                        'info' => 'partially_paid',
                        'success' => 'paid',
                        'danger' => 'void',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'مسودة',
                        'issued' => 'مصدرة',
                        'partially_paid' => 'مدفوعة جزئيًا',
                        'paid' => 'مدفوعة',
                        'void' => 'ملغاة',
                        default => $state,
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('collect')
                    ->label('تحصيل')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool =>
                        (auth()->user()?->can('collections.post') ?? false)
                        && in_array($record->status, ['issued', 'partially_paid'], true)
                        && Decimal::compare((string) $record->total, (string) $record->paid_amount) > 0
                    )
                    ->form(function (Invoice $record): array {
                        $stationId = app(StationContext::class)->currentId();

                        $collectorOptions = CollectorAccount::query()
                            ->where('station_id', $stationId ?? 0)
                            ->where('status', 'open')
                            ->with('collector:id,name')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (CollectorAccount $account): array => [
                                $account->collector_id => $account->collector->name . ' — رصيد عهدة: ' . $account->balance,
                            ])
                            ->all();

                        $cashOptions = CashAccount::query()
                            ->where('station_id', $stationId ?? 0)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();

                        return [
                            Forms\Components\TextInput::make('amount')
                                ->label('مبلغ التحصيل')
                                ->required()
                                ->numeric()
                                ->default(Decimal::sub((string) $record->total, (string) $record->paid_amount))
                                ->minValue(0.0001)
                                ->maxValue((float) Decimal::sub((string) $record->total, (string) $record->paid_amount)),
                            Forms\Components\Select::make('collector_id')
                                ->label('المحصل')
                                ->options($collectorOptions)
                                ->required()
                                ->searchable()
                                ->native(false),
                            Forms\Components\Select::make('cash_account_id')
                                ->label('حساب النقدية / البنك')
                                ->options($cashOptions)
                                ->required()
                                ->searchable()
                                ->native(false),
                            Forms\Components\Select::make('method')
                                ->label('طريقة الدفع')
                                ->options([
                                    'cash' => 'نقدًا',
                                    'bank' => 'بنك',
                                    'transfer' => 'تحويل',
                                    'other' => 'أخرى',
                                ])
                                ->required()
                                ->default('cash')
                                ->native(false),
                            Forms\Components\DateTimePicker::make('paid_at')
                                ->label('وقت التحصيل')
                                ->required()
                                ->default(now()),
                            Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
                        ];
                    })
                    ->action(function (Invoice $record, array $data): void {
                        $stationId = app(StationContext::class)->currentId();

                        abort_unless($stationId && (int) $record->station_id === $stationId, 403);

                        $amount = Decimal::normalize((string) $data['amount']);
                        $remaining = Decimal::sub((string) $record->total, (string) $record->paid_amount);

                        if (Decimal::compare($amount, '0') <= 0 || Decimal::compare($amount, $remaining) > 0) {
                            Notification::make()->danger()->title('مبلغ التحصيل غير صالح')->body('المبلغ يتجاوز الرصيد المتبقي أو يساوي صفرًا.')->send();
                            return;
                        }

                        $receipt = 'RCPT-' . Carbon::parse((string) $data['paid_at'])->format('YmdHis') . '-' . Str::upper(Str::random(6));

                        app(PaymentService::class)->collect(
                            $stationId,
                            (int) $data['collector_id'],
                            (int) $record->customer_id,
                            (int) $record->id,
                            (int) $data['cash_account_id'],
                            (string) Str::uuid(),
                            $receipt,
                            Carbon::parse((string) $data['paid_at'])->format('Y-m-d H:i:s'),
                            $amount,
                            (string) $data['method'],
                            $data['notes'] ?? null,
                        );

                        Notification::make()->success()->title('تم تسجيل التحصيل')->body('تم تحديث الفاتورة وترحيل القيد المحاسبي.')->send();
                    })
                    ->requiresConfirmation(),
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
            ? $query->where('invoices.station_id', $stationId)
            : $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('billing.view') ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
