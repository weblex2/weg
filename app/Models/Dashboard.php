<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dashboard extends Model
{
    protected $fillable = ['name', 'layout', 'is_active'];

    protected $casts = [
        'layout' => 'array',
        'is_active' => 'boolean',
    ];
}
