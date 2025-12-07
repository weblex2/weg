<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ScheduledJob extends Model
{
    protected $fillable = [
        'name',
        'entity_id',
        'action',
        'parameters',
        'scheduled_time',
        'weekdays',
        'is_repeating',
        'is_active',
        'next_run_at',
        'last_run_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'weekdays' => 'array',
        'is_repeating' => 'boolean',
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'scheduled_time' => 'datetime',
    ];

    public function calculateNextRun(): ?Carbon
    {
        $scheduledTime = Carbon::parse($this->scheduled_time);
        $nextRun = Carbon::today()->setTimeFrom($scheduledTime);

        // If scheduled time today has passed, start from tomorrow
        if ($nextRun->isPast()) {
            $nextRun->addDay();
        }

        // If specific weekdays are set, find next matching day
        if (!empty($this->weekdays)) {
            $maxDays = 7;
            $daysChecked = 0;

            while ($daysChecked < $maxDays) {
                // Carbon::dayOfWeekIso: 1 = Monday, 7 = Sunday
                $carbonDay = $nextRun->dayOfWeekIso;

                if (in_array($carbonDay, $this->weekdays)) {
                    return $nextRun;
                }
                $nextRun->addDay();
                $daysChecked++;
            }

            return null; // No valid weekday found
        }

        return $nextRun;
    }
}
