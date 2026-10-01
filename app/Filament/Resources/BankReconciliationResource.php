<?php
namespace App\Filament\Resources;
use App\Filament\Resources\BankReconciliationResource\Pages;
use App\Models\BankReconciliation;
use App\Support\StationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
class BankReconciliationResource extends Resource
{
 protected static ?string $model=BankReconciliation::class;
 protected static ?string $navigationGroup='الخزينة';
 protected static ?string $navigationIcon='heroicon-o-banknotes';
 public static function getNavigationLabel(): string{return 'مطابقات البنوك';}
 public static function getModelLabel(): string{return 'مطابقة بنكية';}
 public static function getPluralModelLabel(): string{return 'مطابقات البنوك';}
 public static function form(Form $form): Form{return $form->schema([Forms\Components\Select::make('cash_account_id')->label('cash_account_id')->relationship('cash_account','name')->required()->searchable(),
            Forms\Components\DatePicker::make('statement_date')->label('statement_date')->required(),
            Forms\Components\TextInput::make('statement_balance')->label('statement_balance')->numeric()->required(),
            Forms\Components\TextInput::make('book_balance')->label('book_balance')->disabled(),
            Forms\Components\TextInput::make('difference')->label('difference')->disabled(),
            Forms\Components\Select::make('status')->label('status')->options(['draft'=>'مسودة','in_progress'=>'قيد التنفيذ','completed'=>'مكتملة','closed'=>'مغلقة','reopened'=>'معاد فتحها','cancelled'=>'ملغاة'])->required()]);}
 public static function table(Table $table): Table{return $table->columns([Tables\Columns\TextColumn::make('cash_account_id')->label('cash_account_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('statement_date')->label('statement_date')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('statement_balance')->label('statement_balance')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('book_balance')->label('book_balance')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('difference')->label('difference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('status')->searchable()->sortable()])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([])]);}
 public static function getEloquentQuery(): Builder{$q=parent::getEloquentQuery();$id=app(StationContext::class)->currentId();return $id?$q->where($q->getModel()->getTable().'.station_id',$id):$q->whereRaw('1=0');}
 public static function canViewAny(): bool{return auth()->user()?->can('accounting.view')??false;}
 public static function canCreate(): bool{return false;}
 public static function canEdit($record): bool{return false;}
 public static function canDelete($record): bool{return false;}
 public static function getPages(): array{return ['index'=>Pages\ListBankReconciliations::route('/'),'create'=>Pages\CreateBankReconciliation::route('/create'),'edit'=>Pages\EditBankReconciliation::route('/{record}/edit')];}
}