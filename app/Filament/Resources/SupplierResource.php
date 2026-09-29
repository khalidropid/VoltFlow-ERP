<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\StationScopedResource;
use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    use StationScopedResource;

    protected static ?string $model = Supplier::class;
    protected static string $viewPermission = 'procurement.view';
    protected static string $managePermission = 'procurement.manage';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'المشتريات والمخزون';

    public static function getNavigationLabel(): string { return 'الموردون'; }
    public static function getModelLabel(): string { return 'مورد'; }
    public static function getPluralModelLabel(): string { return 'الموردون'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('رمز المورد')->required()->maxLength(80),
            Forms\Components\TextInput::make('name')->label('اسم المورد')->required()->maxLength(255),
            Forms\Components\TextInput::make('phone')->label('الهاتف')->tel()->maxLength(30),
            Forms\Components\TextInput::make('email')->label('البريد الإلكتروني')->email()->maxLength(150),
            Forms\Components\TextInput::make('tax_number')->label('الرقم الضريبي')->maxLength(100),
            Forms\Components\Textarea::make('address')->label('العنوان')->rows(2),
            Forms\Components\Select::make('status')->label('الحالة')
                ->options(['active'=>'نشط','inactive'=>'غير نشط'])
                ->required()->default('active')->native(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('المورد')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('phone')->label('الهاتف'),
            Tables\Columns\TextColumn::make('email')->label('البريد'),
            Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                ->colors(['success'=>'active','gray'=>'inactive'])
                ->formatStateUsing(fn(string $state): string => $state === 'active' ? 'نشط' : 'غير نشط'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'=>Pages\ListSuppliers::route('/'),
            'create'=>Pages\CreateSupplier::route('/create'),
            'edit'=>Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
