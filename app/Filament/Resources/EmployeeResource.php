<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = Employee::class;
    protected static string $viewPermission = 'employees.view';
    protected static string $managePermission = 'employees.manage';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'الموارد البشرية والرواتب';
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string { return 'الموظفون'; }
    public static function getModelLabel(): string { return 'موظف'; }
    public static function getPluralModelLabel(): string { return 'الموظفون'; }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();
        return $form->schema([
            Forms\Components\TextInput::make('employee_no')->label('الرقم الوظيفي')->required()->maxLength(50),
            Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            Forms\Components\TextInput::make('phone')->label('الهاتف')->maxLength(50),
            Forms\Components\TextInput::make('email')->label('البريد الإلكتروني')->email()->maxLength(255),
            Forms\Components\Select::make('department_id')->label('القسم')
                ->options(fn () => Department::query()->where('station_id',$stationId ?? 0)->where('is_active',true)->orderBy('name')->pluck('name','id')->all())
                ->searchable()->native(false),
            Forms\Components\Select::make('position_id')->label('الوظيفة')
                ->options(fn () => Position::query()->where('station_id',$stationId ?? 0)->orderBy('name')->pluck('name','id')->all())
                ->searchable()->native(false),
            Forms\Components\DatePicker::make('joined_on')->label('تاريخ الالتحاق')->required(),
            Forms\Components\DatePicker::make('left_on')->label('تاريخ المغادرة'),
            Forms\Components\Select::make('status')->label('الحالة')->options([
                'active'=>'نشط','inactive'=>'غير نشط','terminated'=>'منتهٍ',
            ])->required()->default('active')->native(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('employee_no')->label('الرقم')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('الموظف')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('department.name')->label('القسم'),
            Tables\Columns\TextColumn::make('position.name')->label('الوظيفة'),
            Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                ->color(fn (string $state): string => match($state) {
                    'active'=>'success','inactive'=>'gray','terminated'=>'danger',default=>'gray',
                })
                ->formatStateUsing(fn (string $state): string => match($state) {
                    'active'=>'نشط','inactive'=>'غير نشط','terminated'=>'منتهٍ',default=>$state,
                }),
        ])->actions([Tables\Actions\EditAction::make()])
          ->defaultSort('employee_no');
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListEmployees::route('/'),
            'create'=>Pages\CreateEmployee::route('/create'),
            'edit'=>Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
