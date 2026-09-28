<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Station extends Model
{
    protected $fillable = ['code', 'name', 'name_ar', 'timezone', 'currency_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_default')->withTimestamps();
    }

    public function customers() { return $this->hasMany(Customer::class); }
    public function meters() { return $this->hasMany(Meter::class); }
    public function tariffs() { return $this->hasMany(Tariff::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function chartOfAccounts() { return $this->hasMany(ChartOfAccount::class); }
    public function fiscalPeriods() { return $this->hasMany(FiscalPeriod::class); }
    public function generators() { return $this->hasMany(Generator::class); }
}
