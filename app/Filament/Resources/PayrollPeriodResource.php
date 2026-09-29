<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\PayrollPeriodResource\Pages;
use App\Models\PayrollPeriod;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollPeriodResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = PayrollPeriod::class;
    protected static string $viewPermission = 'payroll.view';
    protected static string $managePermission = 'payroll.process';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'الموارد البشرية والرواتب';
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string { return 'فترات الرواتب'; }
    public static function getModelLabel(): string { return 'فترة رواتب'; }
    public static function getPluralModelLabel(): string { return 'فترات الرواتب'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('الفترة')->required()->maxLength(30),
            Forms\Components\DatePicker::make('starts_on')->label('من')->required(),
            Forms\Components\DatePicker::make('ends_on')->label('إلى')->required()->afterOrEqual('starts_on'),
            Forms\Components\Select::make('status')->label('الحالة')->options([
                'open'=>'مفتوحة','closed'=>'مغلقة',
            ])->required()->default('open')->native(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الفترة')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('starts_on')->label('من')->date(),
            Tables\Columns\TextColumn::make('ends_on')->label('إلى')->date(),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors([
                'success'=>'open','gray'=>'closed',
            ])->formatStateUsing(fn(string $state): string => $state === 'open' ? 'مفتوحة' : 'مغلقة'),
            Tables\Columns\TextColumn::make('payrollRuns_count')->counts('payrollRuns')->label('التشغيلات'),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn(PayrollPeriod $record): bool => $record->status === 'open'),
        ])->defaultSort('starts_on','desc');
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && $record->status === 'open';
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListPayrollPeriods::route('/'),
            'create'=>Pages\CreatePayrollPeriod::route('/create'),
            'edit'=>Pages\EditPayrollPeriod::route('/{record}/edit'),
        ];
    }
}
