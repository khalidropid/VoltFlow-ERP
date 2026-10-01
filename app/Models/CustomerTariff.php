<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class CustomerTariff extends Model
{
    use HasStation;
    protected $fillable=['station_id','customer_id','tariff_id','effective_from','effective_to','is_active'];
    protected function casts(): array { return ['effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
    public function customer(){return $this->belongsTo(Customer::class);}
    public function tariff(){return $this->belongsTo(Tariff::class);}
}