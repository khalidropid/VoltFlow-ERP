<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id','station_id','event','auditable_type','auditable_id','old_values','new_values','request_id','ip_address','user_agent'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }
}
