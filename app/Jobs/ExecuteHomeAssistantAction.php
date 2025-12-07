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
        Log::channel('database')->info('ExecuteHomeAssistantAction started', [
            'type' => 'ha_job_execution',
            'job' => $this->scheduledJob->name,
            'entity' => $this->scheduledJob->entity_id,
        ]);

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
                Log::channel('database')->info("Job executed successfully", [
                    'type' => 'ha_job_execution',
                    'job_id' => $this->scheduledJob->id,
                ]);

                // Update last run
                $this->scheduledJob->update([
                    'last_run_at' => now()
                ]);

                // Wenn wiederholend: nächsten Run berechnen und dispatchen
                if ($this->scheduledJob->is_repeating) {
                    $this->rescheduleJob();
                } else {
                    // Einmaliger Job: löschen
                    Log::channel('database')->info("Deleting non-repeating job", [
                        'type' => 'ha_job_execution',
                        'job_id' => $this->scheduledJob->id,
                        'job_name' => $this->scheduledJob->name,
                    ]);

                    $this->scheduledJob->delete();
                }
            } else {
                Log::channel('database')->error("Job execution failed", [
                    'type' => 'ha_job_execution',
                    'job_id' => $this->scheduledJob->id,
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('database')->error("Job execution error", [
                'type' => 'ha_job_execution',
                'job_id' => $this->scheduledJob->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function rescheduleJob(): void
    {
        $nextRun = $this->scheduledJob->calculateNextRun();

        if ($nextRun) {
            // Stelle sicher, dass $nextRun die richtige Timezone hat
            $nextRun = Carbon::parse($nextRun)->timezone(config('app.timezone'));

            $this->scheduledJob->update([
                'next_run_at' => $nextRun
            ]);

            // WICHTIG: delay() direkt mit Carbon-Objekt, nicht mit Sekunden!
            static::dispatch($this->scheduledJob)->delay($nextRun);

            Log::channel('database')->info("Job rescheduled", [
                'type' => 'ha_job_execution',
                'job_id' => $this->scheduledJob->id,
                'job_name' => $this->scheduledJob->name,
                'next_run' => $nextRun->format('Y-m-d H:i:s'),
                'next_run_timestamp' => $nextRun->timestamp,
                'timezone' => $nextRun->timezone->getName(),
            ]);
        } else {
            Log::channel('database')->warning("Could not calculate next run", [
                'type' => 'ha_job_execution',
                'job_id' => $this->scheduledJob->id,
                'job_name' => $this->scheduledJob->name,
            ]);
        }
    }
}
