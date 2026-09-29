<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\PayrollSlipResource\Pages;
use App\Models\CashAccount;
use App\Models\PayrollSlip;
use App\Services\Payroll\PayrollService;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PayrollSlipResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = PayrollSlip::class;
    protected static string $viewPermission = 'payroll.view';
    protected static string $managePermission = 'payroll.process';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'الموارد البشرية والرواتب';
    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string { return 'كشوف الرواتب'; }
    public static function getModelLabel(): string { return 'كشف راتب'; }
    public static function getPluralModelLabel(): string { return 'كشوف الرواتب'; }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();
        return $stationId
            ? $query->whereHas('payrollRun', fn($q)=>$q->where('station_id',$stationId))
            : $query->whereRaw('1 = 0');
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('slip_number')->label('رقم الكشف')->disabled(),
            Forms\Components\TextInput::make('gross_amount')->label('الإجمالي')->disabled(),
            Forms\Components\TextInput::make('deduction_amount')->label('الخصومات')->disabled(),
            Forms\Components\TextInput::make('net_amount')->label('الصافي')->disabled(),
            Forms\Components\TextInput::make('status')->label('الحالة')->disabled(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('slip_number')->label('رقم الكشف'),
            TextEntry::make('employee.name')->label('الموظف'),
            TextEntry::make('payrollRun.payrollPeriod.code')->label('الفترة'),
            TextEntry::make('gross_amount')->label('الإجمالي'),
            TextEntry::make('deduction_amount')->label('الخصومات'),
            TextEntry::make('net_amount')->label('الصافي'),
            TextEntry::make('status')->label('الحالة'),
            TextEntry::make('journal_entry_id')->label('قيد الرواتب')->placeholder('غير مرحل'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('slip_number')->label('رقم الكشف')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('employee.name')->label('الموظف')->searchable(),
            Tables\Columns\TextColumn::make('payrollRun.payrollPeriod.code')->label('الفترة'),
            Tables\Columns\TextColumn::make('gross_amount')->label('الإجمالي')->numeric(decimalPlaces:4),
            Tables\Columns\TextColumn::make('deduction_amount')->label('الخصومات')->numeric(decimalPlaces:4),
            Tables\Columns\TextColumn::make('net_amount')->label('الصافي')->numeric(decimalPlaces:4),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
                'gray'=>'draft','warning'=>'approved','success'=>'paid',
            ])->formatStateUsing(fn(string $state): string => match($state) {
                'draft'=>'مسودة','approved'=>'معتمد','paid'=>'مدفوع',default=>$state,
            }),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('approve')->label('اعتماد')->color('warning')
                ->visible(fn(PayrollSlip $r): bool => $r->status === 'draft' && (auth()->user()?->can('payroll.process') ?? false))
                ->requiresConfirmation()->action(function(PayrollSlip $r): void {
                    app(PayrollService::class)->approveSlip((int)$r->id);
                    Notification::make()->success()->title('تم اعتماد كشف الراتب')->send();
                }),
            Tables\Actions\Action::make('post')->label('ترحيل محاسبي')->color('success')
                ->visible(fn(PayrollSlip $r): bool => $r->status === 'approved' && (auth()->user()?->can('payroll.post') ?? false))
                ->requiresConfirmation()->action(function(PayrollSlip $r): void {
                    app(PayrollService::class)->postSlipToAccounting((int)$r->id,auth()->user());
                    Notification::make()->success()->title('تم ترحيل كشف الراتب')->send();
                }),
            Tables\Actions\Action::make('pay')->label('دفع الراتب')->color('primary')
                ->visible(fn(PayrollSlip $r): bool => $r->status === 'approved' && $r->journal_entry_id && (auth()->user()?->can('payroll.pay') ?? false))
                ->form([
                    Forms\Components\Select::make('cash_account_id')->label('النقدية / البنك')
                        ->options(fn()=>CashAccount::query()->where('station_id',app(StationContext::class)->currentId() ?? 0)->where('is_active',true)->orderBy('name')->pluck('name','id')->all())
                        ->required()->searchable()->native(false),
                    Forms\Components\Select::make('method')->label('طريقة الدفع')->options([
                        'cash'=>'نقدًا','bank'=>'بنك','transfer'=>'تحويل','other'=>'أخرى',
                    ])->required()->default('bank')->native(false),
                ])->action(function(PayrollSlip $r,array $data): void {
                    abort_unless((int)$r->payrollRun()->firstOrFail()->station_id === (int)app(StationContext::class)->currentId(),403);
                    app(PayrollService::class)->paySlip((int)$r->id,(int)$data['cash_account_id'],(string)Str::uuid(),now()->format('Y-m-d H:i:s'),(string)$data['method'],auth()->user());
                    Notification::make()->success()->title('تم دفع الراتب')->send();
                }),
            Tables\Actions\Action::make('voidPayment')->label('إلغاء الدفع')->color('danger')
                ->visible(fn(PayrollSlip $r): bool => $r->status === 'paid' && (auth()->user()?->can('payroll.void') ?? false))
                ->requiresConfirmation()->action(function(PayrollSlip $r): void {
                    $payment=$r->payment()->firstOrFail();
                    app(PayrollService::class)->voidPayment((int)$payment->id,now()->format('Y-m-d H:i:s'),auth()->user());
                    Notification::make()->success()->title('تم إلغاء دفع الراتب')->send();
                }),
        ])->defaultSort('id','desc');
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListPayrollSlips::route('/'),
            'view'=>Pages\ViewPayrollSlip::route('/{record}'),
        ];
    }
}
