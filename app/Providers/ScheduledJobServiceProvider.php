<?php

namespace App\Providers;

use App\Jobs\ExecuteHomeAssistantAction;
use App\Models\ScheduledJob;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class ScheduledJobServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Nur beim Web-Request oder nach Deployment
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->scheduleAllJobs();
    }

    private function scheduleAllJobs(): void
    {
        $activeJobs = ScheduledJob::where('is_active', true)
            ->whereNotNull('next_run_at')
            ->get();

        foreach ($activeJobs as $job) {
            $delay = Carbon::parse($job->next_run_at)->diffInSeconds(now());

            // Nur dispatchen wenn in der Zukunft
            if ($delay > 0) {
                ExecuteHomeAssistantAction::dispatch($job)->delay($delay);
            }
        }
    }
}
