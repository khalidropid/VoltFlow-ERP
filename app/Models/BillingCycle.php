<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class BillingCycle extends Model
{
    use HasStation;
    protected $fillable=['station_id','billing_period_id','code','name','reading_from','reading_to','billing_date','status'];
    protected function casts(): array { return ['reading_from'=>'date','reading_to'=>'date','billing_date'=>'date']; }
    public function period() { return $this->belongsTo(BillingPeriod::class,'billing_period_id'); }
    public function invoices() { return $this->hasMany(Invoice::class); }
}
