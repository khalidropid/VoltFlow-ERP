<?php
namespace App\Filament\Resources;
use App\Filament\Resources\CashClosingResource\Pages;
use App\Models\CashClosing;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
class CashClosingResource extends Resource
{
 protected static ?string $model=CashClosing::class;
 protected static ?string $navigationGroup='الخزينة';
 protected static ?string $navigationIcon='heroicon-o-banknotes';
 public static function getNavigationLabel(): string{return 'إقفالات النقدية';}
 public static function getModelLabel(): string{return 'إقفال نقدية';}
 public static function getPluralModelLabel(): string{return 'إقفالات النقدية';}
 public static function form(Form $form): Form{return $form->schema([Forms\Components\Select::make('cash_account_id')->label('cash_account_id')->relationship('cash_account','name')->required()->searchable(),
            Forms\Components\DatePicker::make('closing_date')->label('closing_date')->required(),
            Forms\Components\TextInput::make('system_balance')->label('system_balance')->numeric()->required(),
            Forms\Components\TextInput::make('counted_balance')->label('counted_balance')->numeric()->required(),
            Forms\Components\TextInput::make('variance')->label('variance')->disabled(),
            Forms\Components\Select::make('status')->label('status')->options(['draft'=>'مسودة','in_progress'=>'قيد التنفيذ','completed'=>'مكتملة','closed'=>'مغلقة','reopened'=>'معاد فتحها','cancelled'=>'ملغاة'])->required()]);}
 public static function table(Table $table): Table{return $table->columns([Tables\Columns\TextColumn::make('cash_account_id')->label('cash_account_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('closing_date')->label('closing_date')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('system_balance')->label('system_balance')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('counted_balance')->label('counted_balance')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('variance')->label('variance')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('status')->searchable()->sortable()])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([])]);}
 public static function getEloquentQuery(): Builder{$q=parent::getEloquentQuery();$id=app(StationContext::class)->currentId();return $id?$q->where($q->getModel()->getTable().'.station_id',$id):$q->whereRaw('1=0');}
 public static function canViewAny(): bool{return auth()->user()?->can('accounting.view')??false;}
 public static function canCreate(): bool{return (auth()->user()?->can('banking.manage')??false)&&app(StationContext::class)->currentId()!==null;}
 public static function canEdit($record): bool{return auth()->user()?->can('banking.manage')??false;}
 public static function canDelete($record): bool{return false;}
 public static function getPages(): array{return ['index'=>Pages\ListCashClosings::route('/'),'create'=>Pages\CreateCashClosing::route('/create'),'edit'=>Pages\EditCashClosing::route('/{record}/edit')];}
}