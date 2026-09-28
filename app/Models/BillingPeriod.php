<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class BillingPeriod extends Model
{
    use HasStation;
    protected $fillable=['station_id','code','name','starts_on','ends_on','status'];
    protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date']; }
    public function cycles() { return $this->hasMany(BillingCycle::class,'billing_period_id'); }
}
