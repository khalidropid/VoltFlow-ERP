<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    protected $fillable = ['station_id', 'code', 'name', 'name_ar', 'type', 'parent_id', 'is_postable', 'is_active'];

    protected function casts(): array
    {
        return ['is_postable' => 'boolean', 'is_active' => 'boolean'];
    }
}
