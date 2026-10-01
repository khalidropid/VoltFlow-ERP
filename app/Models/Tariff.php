<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasStation;

    protected $fillable = ['station_id','code','name','effective_from','effective_to','is_active'];

    protected function casts(): array
    {
        return ['effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean'];
    }

    public function slabs() { return $this->hasMany(TariffSlab::class)->orderBy('sort_order'); }
}
