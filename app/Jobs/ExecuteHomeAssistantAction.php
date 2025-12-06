<?php

namespace App\Jobs;

use App\Models\ScheduledJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExecuteHomeAssistantAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ScheduledJob $scheduledJob
    ) {}

    public function handle(): void
    {
        $haUrl = config('homeassistant.url');
        $haToken = config('homeassistant.token');

        $domain = explode('.', $this->scheduledJob->entity_id)[0];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $haToken,
                'Content-Type' => 'application/json',
            ])->post("{$haUrl}/api/services/{$domain}/{$this->scheduledJob->action}", [
                'entity_id' => $this->scheduledJob->entity_id,
                ...$this->scheduledJob->parameters ?? []
            ]);

            if ($response->successful()) {
                Log::info("Job executed successfully", [
                    'job_id' => $this->scheduledJob->id,
                    'entity_id' => $this->scheduledJob->entity_id
                ]);

                // Update last run time
                $this->scheduledJob->update([
                    'last_run_at' => now()
                ]);

                // Reschedule if repeating
                if ($this->scheduledJob->is_repeating) {
                    $this->rescheduleJob();
                }
            } else {
                Log::error("Job execution failed", [
                    'job_id' => $this->scheduledJob->id,
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Job execution error", [
                'job_id' => $this->scheduledJob->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function rescheduleJob(): void
    {
        $nextRun = $this->calculateNextRun();

        if ($nextRun) {
            $this->scheduledJob->update([
                'next_run_at' => $nextRun
            ]);

            // Schedule the next execution
            $delay = $nextRun->diffInSeconds(now());
            static::dispatch($this->scheduledJob)->delay($delay);

            Log::info("Job rescheduled", [
                'job_id' => $this->scheduledJob->id,
                'next_run' => $nextRun
            ]);
        }
    }

    private function calculateNextRun(): ?Carbon
    {
        $scheduledTime = Carbon::parse($this->scheduledJob->scheduled_time);
        $nextRun = Carbon::today()->setTimeFrom($scheduledTime);

        // If scheduled time today has passed, start from tomorrow
        if ($nextRun->isPast()) {
            $nextRun->addDay();
        }

        // If specific weekdays are set, find next matching day
        if (!empty($this->scheduledJob->weekdays)) {
            $maxDays = 7;
            $daysChecked = 0;

            while ($daysChecked < $maxDays) {
                // Carbon: 1 = Monday, 7 = Sunday
                if (in_array($nextRun->dayOfWeek, $this->scheduledJob->weekdays)) {
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
