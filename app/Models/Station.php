<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    protected $fillable = ['code', 'name', 'name_ar', 'timezone', 'currency_code', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
}
