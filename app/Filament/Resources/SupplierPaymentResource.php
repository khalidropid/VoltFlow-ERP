<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\SupplierPaymentResource\Pages;
use App\Models\CashAccount;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Support\Decimal;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SupplierPaymentResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = SupplierPayment::class;
    protected static string $viewPermission = 'procurement.view';
    protected static string $managePermission = 'procurement.manage';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';
    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string { return 'مدفوعات الموردين'; }
    public static function getModelLabel(): string { return 'دفعة مورد'; }
    public static function getPluralModelLabel(): string { return 'مدفوعات الموردين'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();

        return $form->schema([
            Forms\Components\Select::make('supplier_id')->label('المورد')
                ->options(fn () => Supplier::query()->where('station_id',$stationId ?? 0)->where('status','active')
                    ->orderBy('name')->pluck('name','id')->all())
                ->required()->searchable()->native(false)->live(),
            Forms\Components\Select::make('cash_account_id')->label('النقدية / البنك')
                ->options(fn () => CashAccount::query()->where('station_id',$stationId ?? 0)->where('is_active',true)
                    ->orderBy('name')->pluck('name','id')->all())
                ->required()->searchable()->native(false),
            Forms\Components\TextInput::make('receipt_number')->label('رقم الإيصال')->required()->maxLength(80),
            Forms\Components\DateTimePicker::make('paid_at')->label('وقت الدفع')->required()->default(now()),
            Forms\Components\Select::make('method')->label('طريقة الدفع')->options([
                'cash'=>'نقدًا','bank'=>'بنك','transfer'=>'تحويل','other'=>'أخرى',
            ])->required()->default('cash')->native(false),
            Forms\Components\TextInput::make('amount')->label('إجمالي الدفعة')->numeric()->required()->minValue(0.0001),
            Forms\Components\Repeater::make('allocations')->label('توزيع الدفعة')
                ->schema([
                    Forms\Components\Select::make('supplier_invoice_id')->label('الفاتورة')
                        ->options(function (Get $get) {
                            $supplierId=(int)($get('../../supplier_id') ?: 0);
                            return SupplierInvoice::query()->where('station_id', app(StationContext::class)->currentId() ?? 0)
                                ->where('supplier_id',$supplierId)->whereIn('status',['posted','partially_paid'])
                                ->orderBy('invoice_date')->pluck('number','id')->all();
                        })->required()->searchable()->native(false),
                    Forms\Components\TextInput::make('amount')->label('المبلغ')->numeric()->required()->minValue(0.0001),
                ])->columns(2)->minItems(1),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('receipt_number')->label('رقم الإيصال'),
            TextEntry::make('supplier.name')->label('المورد'),
            TextEntry::make('cashAccount.name')->label('النقدية / البنك'),
            TextEntry::make('paid_at')->label('وقت الدفع')->dateTime(),
            TextEntry::make('amount')->label('المبلغ'),
            TextEntry::make('method')->label('الطريقة')->formatStateUsing(fn (string $state): string => match ($state) {
                'cash'=>'نقدًا','bank'=>'بنك','transfer'=>'تحويل','other'=>'أخرى',default=>$state,
            }),
            TextEntry::make('status')->label('الحالة')->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'مرحل' : 'ملغى'),
            TextEntry::make('journal_entry_id')->label('قيد الدفع')->placeholder('غير موجود'),
            TextEntry::make('reversal_journal_entry_id')->label('قيد العكس')->placeholder('غير موجود'),
            TextEntry::make('voided_at')->label('وقت الإلغاء')->dateTime()->placeholder('غير ملغى'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('receipt_number')->label('رقم الإيصال')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
            Tables\Columns\TextColumn::make('cashAccount.name')->label('النقدية / البنك'),
            Tables\Columns\TextColumn::make('paid_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
            Tables\Columns\TextColumn::make('amount')->label('المبلغ')->numeric(decimalPlaces:4)->sortable(),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
                'success'=>'posted','danger'=>'voided'
            ])->formatStateUsing(fn(string $state): string => $state === 'posted' ? 'مرحل' : 'ملغى'),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('void')
                ->label('إلغاء الدفعة')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                ->visible(fn(SupplierPayment $record): bool =>
                    (auth()->user()?->can('procurement.manage') ?? false) && $record->status === 'posted')
                ->requiresConfirmation()
                ->action(function(SupplierPayment $record): void {
                    $stationId=app(StationContext::class)->currentId();
                    abort_unless($stationId && (int)$record->station_id === $stationId,403);
                    app(\App\Services\Procurement\SupplierPaymentService::class)
                        ->void((int)$record->id, now()->format('Y-m-d H:i:s'), auth()->user());
                    Notification::make()->success()->title('تم إلغاء دفعة المورد')
                        ->body('تم عكس القيد وإعادة المبلغ للذمة والنقدية.')->send();
                }),
        ])->defaultSort('paid_at','desc');
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListSupplierPayments::route('/'),
            'create'=>Pages\CreateSupplierPayment::route('/create'),
            'view'=>Pages\ViewSupplierPayment::route('/{record}'),
        ];
    }
}
