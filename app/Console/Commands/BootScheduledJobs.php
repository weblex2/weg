<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteHomeAssistantAction;
use App\Models\ScheduledJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BootScheduledJobs extends Command
{
    protected $signature = 'ha:boot';
    protected $description = 'Boot all scheduled Home Assistant jobs';

    public function handle()
    {
        Log::channel('database')->info('HA:Boot Command Started', ['type' => 'ha_boot']);

        // ERST: Komplette Queue leeren
        $this->info('🧹 Clearing queue...');
        $deletedQueueJobs = DB::table('jobs')->delete();
        $this->info("   Deleted {$deletedQueueJobs} queue job(s)");
        $this->newLine();

        // DANN: Aktive ScheduledJobs holen
        $activeJobs = ScheduledJob::where('is_active', true)
            ->whereNotNull('next_run_at')
            ->get();

        $this->info("Found {$activeJobs->count()} active job(s)");

        $dispatched = 0;
        $skipped = 0;

        foreach ($activeJobs as $job) {
            // WICHTIG: Timezone explizit setzen
            $nextRun = Carbon::parse($job->next_run_at)->timezone(config('app.timezone'));
            $now = now();

            $this->line("DEBUG: Job {$job->name}");
            $this->line("  next_run_at from DB: {$job->next_run_at}");
            $this->line("  Parsed: {$nextRun->format('d.m.Y H:i:s')} (TZ: {$nextRun->timezone})");
            $this->line("  Timestamp: {$nextRun->timestamp}");

            Log::channel('database')->info("Processing job", [
                'type' => 'ha_boot',
                'job_id' => $job->id,
                'job_name' => $job->name,
                'entity_id' => $job->entity_id,
                'next_run_at' => $nextRun->format('Y-m-d H:i:s'),
                'is_repeating' => $job->is_repeating,
            ]);

            // Wenn der Zeitpunkt in der Vergangenheit liegt → überspringen
            if ($nextRun->isPast()) {
                $this->warn("⚠ Skipped (past run): {$job->name} - scheduled for {$nextRun->format('d.m.Y H:i')}");

                Log::channel('database')->warning("Job skipped because next_run_at is in the past", [
                    'type' => 'ha_boot',
                    'job_id' => $job->id,
                    'job_name' => $job->name,
                    'scheduled_time' => $nextRun->format('Y-m-d H:i:s'),
                    'now' => $now->format('Y-m-d H:i:s'),
                    'note' => 'Job in scheduled_jobs remains, but not dispatched',
                ]);

                $skipped++;
                continue;
            }

            // delay() mit Carbon-Objekt
            ExecuteHomeAssistantAction::dispatch($job)->delay($nextRun);

            $this->info("✓ Dispatched: {$job->name} at {$nextRun->format('d.m.Y H:i')}");

            Log::channel('database')->info("Job dispatched successfully", [
                'type' => 'ha_boot',
                'job_id' => $job->id,
                'job_name' => $job->name,
                'next_run_at' => $nextRun->format('Y-m-d H:i:s'),
                'timestamp' => $nextRun->timestamp,
            ]);

            $dispatched++;
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->info("✓ Dispatched: {$dispatched}");
        if ($skipped > 0) {
            $this->warn("⚠ Skipped (past): {$skipped}");
        }
        $this->newLine();
        $this->info('All jobs processed successfully!');

        Log::channel('database')->info('HA:Boot Command Completed', [
            'type' => 'ha_boot',
            'total_jobs' => $activeJobs->count(),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);

        return 0;
    }
}
