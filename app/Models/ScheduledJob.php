<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledJob extends Model
{
    protected $guarded = ['id'];

    // In App\Models\ScheduledJob
    protected $casts = [
        'scheduled_time' => 'datetime',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'weekdays' => 'array',
        'parameters' => 'array',
        'is_active' => 'boolean',
        'is_repeating' => 'boolean',
    ];

    public function getWeekdaysDisplayAttribute()
    {
        if (empty($this->weekdays)) {
            return 'Täglich';
        }

        $days = [
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        ];

        return collect($this->weekdays)
            ->map(fn($day) => $days[$day])
            ->join(', ');
    }

}
