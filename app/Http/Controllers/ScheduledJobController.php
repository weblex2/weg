<?php

namespace App\Http\Controllers;

use App\Models\ScheduledJob;
use App\Models\Logs;
use App\Jobs\ExecuteHomeAssistantAction;
use App\Http\Controllers\HomeAssistantController;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class ScheduledJobController extends Controller
{
    public function index(Request $request) {
    \Log::info('=== START ===');

    try {
        // Schritt 1
        \Log::info('Loading scheduled jobs...');
        $scheduledJobs = ScheduledJob::orderBy('next_run_at')->paginate(15);
        \Log::info('Scheduled jobs loaded', ['count' => $scheduledJobs->count()]);

        // Schritt 2
        \Log::info('Loading queue jobs...');
        $queueJobs = \DB::table('jobs')
            ->orderBy('available_at', 'asc')
            ->get()
            ->map(function ($job) {
                $job->available_at_formatted = \Carbon\Carbon::createFromTimestamp($job->available_at)
                    ->timezone(config('app.timezone'))
                    ->format('d.m.Y H:i:s');
                return $job;
            });
        \Log::info('Queue jobs loaded', ['count' => $queueJobs->count()]);

        // Schritt 3
        \Log::info('Getting Redis connection...');
        $redis = \Illuminate\Support\Facades\Redis::connection();
        \Log::info('Redis connected');

        // Schritt 4 - Entities laden (HIER IST WAHRSCHEINLICH DER FEHLER!)
        \Log::info('Loading entities...');
        $entities = $this->loadEntities($redis);
        \Log::info('Entities loaded', ['count' => is_array($entities) ? count($entities) : 0]);

        // Rest of your code...

    } catch (\Exception $e) {
        \Log::error('ERROR in index()', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Return error to user
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}

    private function parseEntityResponse($entitiesResponse)
{
    // Prüfen ob es eine JsonResponse oder bereits ein Array ist
    if ($entitiesResponse instanceof \Illuminate\Http\JsonResponse) {
        $entitiesData = json_decode($entitiesResponse->getContent(), true);
    } elseif (is_array($entitiesResponse)) {
        $entitiesData = $entitiesResponse;
    } else {
        \Log::channel('database')->error('HA: Unbekannter Response-Typ in parseEntityResponse', [
            'type' => gettype($entitiesResponse),
            'class' => is_object($entitiesResponse) ? get_class($entitiesResponse) : null,
        ]);
        return [];
    }

    \Log::channel('database')->info('HA: parseEntityResponse - entitiesData', [
        'has_entities_key' => isset($entitiesData['entities']),
        'entities_count' => isset($entitiesData['entities']) ? count($entitiesData['entities']) : 0,
        'data_keys' => array_keys($entitiesData),
        'full_response' => $entitiesData, // KOMPLETTE Response loggen
        'success' => $entitiesData['success'] ?? null,
        'error' => $entitiesData['error'] ?? null,
    ]);

    // Prüfen ob API-Call erfolgreich war
    if (isset($entitiesData['success']) && $entitiesData['success'] === false) {
        \Log::channel('database')->error('HA: API returned error', [
            'error' => $entitiesData['error'] ?? 'Unknown error',
            'full_response' => $entitiesData,
        ]);
        return [];
    }

    $entitiesRaw = $entitiesData['entities'] ?? [];

    // Entities nach Domain gruppieren und sortieren
    $entities = collect($entitiesRaw)
        ->groupBy(function($entity) {
            return explode('.', $entity['entity_id'])[0];
        })
        ->map(function($domainEntities) {
            return $domainEntities->sortBy(function($entity) {
                return $entity['attributes']['friendly_name'] ?? $entity['entity_id'];
            })->values();
        })
        ->sortKeys()
        ->toArray();

    \Log::channel('database')->info('HA: parseEntityResponse - Ergebnis', [
        'grouped_domains' => array_keys($entities),
        'total_entities' => array_sum(array_map('count', $entities)),
    ]);

    return $entities;
}

    private function createDummyJob()
    {
        $scheduledJob = new ScheduledJob([
            'entity_id'      => '',
            'action'         => '',
            'scheduled_time' => now(),
            'weekdays'       => [],
            'is_repeating'   => false,
            'parameters'     => [],
            'is_active'      => false,
        ]);

        $scheduledJob->id = 0;
        $scheduledJob->exists = true;

        return $scheduledJob;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'entity_id' => 'required|string',
            'action' => 'required|string',
            'scheduled_time' => 'required|date_format:H:i',
            'weekdays' => 'nullable|array',
            'weekdays.*' => 'integer|between:1,7',
            'parameters_json' => 'nullable|string',
            'is_repeating' => 'boolean',
        ]);

        // JSON-Parameter verarbeiten
        $parameters = [];
        if (!empty($validated['parameters_json'])) {
            $decoded = json_decode($validated['parameters_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['parameters_json' => 'Ungültiges JSON-Format'])->withInput();
            }
            $parameters = $decoded;
        }

        $job = ScheduledJob::create([
            'name' => $validated['name'],
            'entity_id' => $validated['entity_id'],
            'action' => $validated['action'],
            'parameters' => $parameters,
            'scheduled_time' => $validated['scheduled_time'],
            'weekdays' => $validated['weekdays'] ?? null,
            'is_repeating' => $request->boolean('is_repeating'),
            'is_active' => true,
        ]);

        // Calculate next_run_at und dispatchen
        $nextRun = $job->calculateNextRun();
        $job->update(['next_run_at' => $nextRun]);

        if ($nextRun) {
            $delay = $nextRun->diffInSeconds(now());
            if ($delay > 0) {
                ExecuteHomeAssistantAction::dispatch($job)->delay($delay);
                Log::channel('database')->info("Job dispatched", [
                    'type' => 'ha_job_created',
                    'job' => $job->name,
                    'next_run' => $nextRun->format('Y-m-d H:i:s'),
                    'delay_seconds' => $delay
                ]);
            }
        }
        Artisan::call('ha:boot');

        return redirect()->route('scheduled-jobs.index')
            ->with('success', 'Aktion erfolgreich erstellt!');
    }

    public function update(Request $request, ScheduledJob $scheduledJob)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'entity_id' => 'required|string',
            'action' => 'required|string',
            'scheduled_time' => 'required|date_format:H:i',
            'weekdays' => 'nullable|array',
            'weekdays.*' => 'integer|between:1,7',
            'parameters_json' => 'nullable|string',
            'is_repeating' => 'boolean',
        ]);

        // JSON-Parameter verarbeiten
        $parameters = [];
        if (isset($validated['parameters_json']) && !empty($validated['parameters_json'])) {
            $decoded = json_decode($validated['parameters_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['parameters_json' => 'Ungültiges JSON-Format'])->withInput();
            }
            $parameters = $decoded;
        }

        $scheduledJob->update([
            'name' => $validated['name'],
            'entity_id' => $validated['entity_id'],
            'action' => $validated['action'],
            'parameters' => $parameters,
            'scheduled_time' => $validated['scheduled_time'],
            'weekdays' => $validated['weekdays'] ?? null,
            'is_repeating' => $request->boolean('is_repeating'),
        ]);

        // Neu berechnen und dispatchen wenn aktiv
        if ($scheduledJob->is_active) {
            $nextRun = $scheduledJob->calculateNextRun();
            $scheduledJob->update(['next_run_at' => $nextRun]);

            if ($nextRun) {
                $delay = $nextRun->diffInSeconds(now());
                if ($delay > 0) {
                    ExecuteHomeAssistantAction::dispatch($scheduledJob)->delay($delay);
                    Log::channel('database')->info("Job rescheduled after update", [
                        'type' => 'ha_job_updated',
                        'job' => $scheduledJob->name,
                        'next_run' => $nextRun->format('Y-m-d H:i:s'),
                        'delay_seconds' => $delay
                    ]);
                }
            }
        }

        Artisan::call('ha:boot');

        return redirect()->route('scheduled-jobs.index')
            ->with('success', 'Aktion erfolgreich aktualisiert!');
    }

    public function destroy(ScheduledJob $scheduledJob)
    {
        $scheduledJob->delete();
        return redirect()->route('scheduled-jobs.index')
            ->with('success', 'Aktion erfolgreich gelöscht!');
    }

    public function toggle(ScheduledJob $scheduledJob)
    {
        $scheduledJob->update([
            'is_active' => !$scheduledJob->is_active
        ]);

        if ($scheduledJob->is_active) {
            $nextRun = $scheduledJob->calculateNextRun();
            $scheduledJob->update(['next_run_at' => $nextRun]);

            if ($nextRun) {
                $delay = $nextRun->diffInSeconds(now());
                if ($delay > 0) {
                    ExecuteHomeAssistantAction::dispatch($scheduledJob)->delay($delay);
                    Log::channel('database')->info("Job activated and dispatched", [
                        'type' => 'ha_job_toggled',
                        'job' => $scheduledJob->name,
                        'next_run' => $nextRun->format('Y-m-d H:i:s'),
                        'delay_seconds' => $delay
                    ]);
                }
            }
        }

        return back()->with('success', 'Status erfolgreich geändert!');
    }

    public function workerStatus()
    {
        try {
            $active = false;

            // Prüfe Betriebssystem
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: Prüfe auf php.exe Prozesse mit queue:work
                $command = 'wmic process where "name=\'php.exe\'" get commandline';
                exec($command, $output);

                foreach ($output as $line) {
                    if (stripos($line, 'queue:work') !== false ||
                        stripos($line, 'queue:listen') !== false) {
                        $active = true;
                        break;
                    }
                }
            } else {
                // Linux/Unix: Prüfe auf laufende Queue-Prozesse
                $command = "ps aux | grep 'queue:work' | grep -v grep";
                exec($command, $output);
                $active = count($output) > 0;

                // Alternativ auch queue:listen prüfen
                if (!$active) {
                    $command = "ps aux | grep 'queue:listen' | grep -v grep";
                    exec($command, $output);
                    $active = count($output) > 0;
                }
            }

            return response()->json([
                'active' => $active,
                'platform' => PHP_OS
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'active' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
