<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'name_ar',
        'type',
        'parent_id',
        'is_postable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForStationOrGlobal(Builder $query, int $stationId): Builder
    {
        return $query->where(function (Builder $q) use ($stationId) {
            $q->where('station_id', $stationId)->orWhereNull('station_id');
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function cashAccounts(): HasMany
    {
        return $this->hasMany(CashAccount::class, 'account_id');
    }

    public function collectorAccounts(): HasMany
    {
        return $this->hasMany(CollectorAccount::class, 'account_id');
    }

    public function debitRules(): HasMany
    {
        return $this->hasMany(AccountingRule::class, 'debit_account_id');
    }

    public function creditRules(): HasMany
    {
        return $this->hasMany(AccountingRule::class, 'credit_account_id');
    }

    public function customerReceivableLinks(): HasMany
    {
        return $this->hasMany(CustomerAccountLink::class, 'receivable_account_id');
    }

    public function customerRevenueLinks(): HasMany
    {
        return $this->hasMany(CustomerAccountLink::class, 'revenue_account_id');
    }

    public function supplierPayableLinks(): HasMany
    {
        return $this->hasMany(SupplierAccountLink::class, 'payable_account_id');
    }

    public function supplierExpenseLinks(): HasMany
    {
        return $this->hasMany(SupplierAccountLink::class, 'expense_account_id');
    }
}
