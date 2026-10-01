<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = ['key','scope','user_id','request_hash','status_code','response_body','expires_at'];

    protected function casts(): array
    {
        return ['response_body' => 'array', 'expires_at' => 'datetime'];
    }
}
