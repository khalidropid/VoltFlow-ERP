<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\PayrollRunResource\Pages;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Support\StationContext;
use App\Services\Payroll\PayrollService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class PayrollRunResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = PayrollRun::class;
    protected static string $viewPermission = 'payroll.view';
    protected static string $managePermission = 'payroll.process';
    protected static ?string $navigationIcon = 'heroicon-o-play';
    protected static ?string $navigationGroup = 'الموارد البشرية والرواتب';
    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string { return 'تشغيلات الرواتب'; }
    public static function getModelLabel(): string { return 'تشغيل رواتب'; }
    public static function getPluralModelLabel(): string { return 'تشغيلات الرواتب'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();
        return $form->schema([
            Forms\Components\Select::make('payroll_period_id')->label('فترة الرواتب')
                ->options(fn()=>PayrollPeriod::query()->where('station_id',$stationId ?? 0)->where('status','open')->orderByDesc('starts_on')->pluck('code','id')->all())
                ->required()->searchable()->native(false),
            Forms\Components\DateTimePicker::make('run_at')->label('وقت التشغيل')->required()->default(now()),
            Forms\Components\Select::make('status')->label('الحالة')->options([
                'draft'=>'مسودة','processing'=>'قيد المعالجة',
            ])->required()->default('draft')->native(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('payrollPeriod.code')->label('الفترة')->sortable(),
            Tables\Columns\TextColumn::make('run_at')->label('وقت التشغيل')->dateTime(),
            Tables\Columns\TextColumn::make('slips_count')->counts('slips')->label('الكشوف'),
            Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                ->color(fn(string $state): string => match($state) {
                    'draft'=>'gray','processing'=>'warning','completed'=>'success','cancelled'=>'danger',default=>'gray',
                })
                ->formatStateUsing(fn(string $state): string => match($state) {
                    'draft'=>'مسودة','processing'=>'قيد المعالجة','completed'=>'مكتمل','cancelled'=>'ملغى',default=>$state,
                }),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn(PayrollRun $record): bool => in_array($record->status,['draft','processing'],true)),
            Tables\Actions\Action::make('generateSlip')->label('توليد كشف')->icon('heroicon-o-document-plus')
                ->visible(fn(PayrollRun $record): bool => in_array($record->status,['draft','processing'],true))
                ->form([
                    Forms\Components\Select::make('employee_id')->label('الموظف')
                        ->options(fn()=>Employee::query()->where('station_id',app(StationContext::class)->currentId() ?? 0)->where('status','active')->orderBy('name')->pluck('name','id')->all())
                        ->required()->searchable()->native(false),
                    Forms\Components\TextInput::make('slip_number')->label('رقم الكشف')->maxLength(80),
                ])->action(function(PayrollRun $record,array $data): void {
                    abort_unless((int)$record->station_id === (int)app(StationContext::class)->currentId(),403);
                    app(PayrollService::class)->generateSlip((int)$record->id,(int)$data['employee_id'],$data['slip_number'] ?: null);
                    Notification::make()->success()->title('تم توليد كشف الراتب')->send();
                }),
        ])->defaultSort('run_at','desc');
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && in_array($record->status,['draft','processing'],true);
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListPayrollRuns::route('/'),
            'create'=>Pages\CreatePayrollRun::route('/create'),
            'edit'=>Pages\EditPayrollRun::route('/{record}/edit'),
        ];
    }
}
