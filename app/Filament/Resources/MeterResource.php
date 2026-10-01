<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeterResource\Pages;
use App\Models\Meter;
use App\Models\User;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeterResource extends Resource
{
    protected static ?string $model = Meter::class;
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string { return 'العدادات'; }
    public static function getModelLabel(): string { return 'عداد'; }
    public static function getPluralModelLabel(): string { return 'العدادات'; }

    private static function currentUser(): ?User
    {
        $user = request()->user();
        return $user instanceof User ? $user : null;
    }

    public static function form(Form $form): Form
    {
        $stationId = app(StationContext::class)->currentId();
        return $form->schema([
            Forms\Components\Select::make('customer_id')->label('العميل')->relationship(
                name: 'customer', titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) => $query->where('customers.station_id', $stationId ?? 0),
            )->searchable()->preload()->required()->native(false),
            Forms\Components\TextInput::make('serial_number')->label('الرقم التسلسلي')->required()->maxLength(100),
            Forms\Components\TextInput::make('meter_number')->label('رقم العداد')->maxLength(100),
            Forms\Components\Select::make('meter_type')->label('نوع العداد')->options(['energy'=>'طاقة','prepaid'=>'مسبق الدفع','smart'=>'ذكي','other'=>'أخرى'])->required()->default('energy')->native(false),
            Forms\Components\Select::make('phase')->label('الطور')->options(['single'=>'فاز واحد','three'=>'ثلاثة فاز'])->native(false),
            Forms\Components\TextInput::make('multiplier')->label('معامل الضرب')->numeric()->inputMode('decimal')->default(1)->required(),
            Forms\Components\TextInput::make('initial_reading')->label('القراءة الابتدائية')->numeric()->inputMode('decimal')->default(0)->required(),
            Forms\Components\DatePicker::make('installed_at')->label('تاريخ التركيب'),
            Forms\Components\Select::make('status')->label('الحالة')->options(['active'=>'نشط','removed'=>'مزال','faulty'=>'عاطل'])->required()->default('active')->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('meter_number')->label('رقم العداد')->searchable(),
            Tables\Columns\TextColumn::make('serial_number')->label('التسلسلي')->searchable(),
            Tables\Columns\TextColumn::make('customer.name')->label('العميل')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('meter_type')->label('النوع'),
            Tables\Columns\TextColumn::make('phase')->label('الطور'),
            Tables\Columns\TextColumn::make('multiplier')->label('المعامل')->numeric(decimalPlaces: 4),
            Tables\Columns\TextColumn::make('installed_at')->label('التركيب')->date(),
            Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                ->color(fn (string $state): string => match ($state) {'active'=>'success','removed'=>'gray','faulty'=>'danger',default=>'gray'})
                ->formatStateUsing(fn (string $state): string => match ($state) {'active'=>'نشط','removed'=>'مزال','faulty'=>'عاطل',default=>$state}),
        ])->actions([Tables\Actions\EditAction::make()])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();
        return $stationId ? $query->where('meters.station_id', $stationId) : $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool { return self::currentUser()?->can('customers.view') ?? false; }
    public static function canCreate(): bool { return (self::currentUser()?->can('customers.manage') ?? false) && app(StationContext::class)->currentId() !== null; }
    public static function canEdit($record): bool { return self::currentUser()?->can('customers.manage') ?? false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return ['index'=>Pages\ListMeters::route('/'),'create'=>Pages\CreateMeter::route('/create'),'edit'=>Pages\EditMeter::route('/{record}/edit')];
    }
}
