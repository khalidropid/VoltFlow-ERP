<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'العملاء والفوترة';
    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string { return 'العملاء'; }
    public static function getModelLabel(): string { return 'عميل'; }
    public static function getPluralModelLabel(): string { return 'العملاء'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الحساب')
                ->schema([
                    Forms\Components\TextInput::make('code')->label('رمز العميل')->required()->maxLength(50),
                    Forms\Components\TextInput::make('account_number')->label('رقم الحساب')->maxLength(100),
                    Forms\Components\TextInput::make('name')->label('اسم العميل')->required()->maxLength(255),
                    Forms\Components\Select::make('customer_type')
                        ->label('نوع العميل')
                        ->options([
                            'residential' => 'سكني',
                            'commercial' => 'تجاري',
                            'industrial' => 'صناعي',
                            'government' => 'حكومي',
                            'other' => 'أخرى',
                        ])
                        ->native(false),
                    Forms\Components\Select::make('status')
                        ->label('الحالة')
                        ->options([
                            'active' => 'نشط',
                            'suspended' => 'موقوف',
                            'closed' => 'مغلق',
                        ])
                        ->required()
                        ->default('active')
                        ->native(false),
                ])->columns(2),

            Forms\Components\Section::make('بيانات الاتصال')
                ->schema([
                    Forms\Components\TextInput::make('phone')->label('الهاتف')->tel()->maxLength(30),
                    Forms\Components\Textarea::make('address')->label('العنوان')->rows(2),
                    Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
                ])->columns(2),

            Forms\Components\Section::make('الرصيد الافتتاحي')
                ->schema([
                    Forms\Components\TextInput::make('opening_balance')
                        ->label('الرصيد الافتتاحي')
                        ->numeric()
                        ->inputMode('decimal')
                        ->default(0)
                        ->required()
                        ->helperText('يجب إدخال قيمة عشرية دون استخدام حسابات عائمة.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('account_number')->label('رقم الحساب')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('العميل')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->searchable(),
                Tables\Columns\TextColumn::make('opening_balance')->label('الرصيد الافتتاحي')->numeric(decimalPlaces: 4),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors(['success' => 'active', 'warning' => 'suspended', 'danger' => 'closed'])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'suspended' => 'موقوف',
                        'closed' => 'مغلق',
                        default => $state,
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();

        return $stationId
            ? $query->where('customers.station_id', $stationId)
            : $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('customers.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return (auth()->user()?->can('customers.manage') ?? false)
            && app(StationContext::class)->currentId() !== null;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('customers.manage') ?? false;
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
