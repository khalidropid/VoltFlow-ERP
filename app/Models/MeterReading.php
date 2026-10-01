<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    use HasStation;

    protected $fillable = ['transaction_uuid','station_id','meter_id','captured_by','reading_at','reading_value','previous_reading_value','consumption','source','status','notes'];

    protected function casts(): array
    {
        return ['reading_at'=>'datetime','reading_value'=>'decimal:4','previous_reading_value'=>'decimal:4','consumption'=>'decimal:4'];
    }

    public function meter() { return $this->belongsTo(Meter::class); }
    public function capturedBy() { return $this->belongsTo(User::class,'captured_by'); }
    public function invoice() { return $this->hasOne(Invoice::class,'reading_id'); }
}
