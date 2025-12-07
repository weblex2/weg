<?php

namespace App\Http\Controllers;

use App\Models\ScheduledJob;
use App\Models\Logs;
use App\Jobs\ExecuteHomeAssistantAction;
use App\Http\Controllers\HomeAssistantController;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScheduledJobController extends Controller
{
    public function index(Request $request)
    {
        // Jobs-Pagination (verwendet 'page')
        $scheduledJobs = ScheduledJob::orderBy('next_run_at')->paginate(15);

        // Queue Jobs laden
        $queueJobs = \DB::table('jobs')
            ->orderBy('available_at', 'asc')
            ->get()
            ->map(function ($job) {
                $job->available_at_formatted = \Carbon\Carbon::createFromTimestamp($job->available_at)
                    ->timezone(config('app.timezone'))
                    ->format('d.m.Y H:i:s');
                return $job;
            });

        // Entities aus Cache oder API laden
        $cacheKey = 'homeassistant:entities';
        $cacheDuration = 3000; // 5 Minuten

        // Prüfen ob in Redis vorhanden
        $loadedFrom = \Cache::has($cacheKey) ? 'redis' : 'api';

        \Log::channel('database')->info('HA: Entities Load Check', [
            'cache_key'   => $cacheKey,
            'loaded_from' => $loadedFrom,
        ]);

        $entities = \Cache::remember($cacheKey, $cacheDuration, function () {
            $api = new HomeAssistantController();
            $entitiesResponse = $api->listEntities();
            return $this->parseEntityResponse($entitiesResponse);
        });
        /* $entities = \Cache::rememberForever('homeassistant:entities', function () {
            $api = new HomeAssistantController();
            $entitiesResponse = $api->listEntities();
            return $this->parseEntityResponse($entitiesResponse);
        }); */

        // Job laden (copy, edit oder neu)
        if ($request->has('copy')) {
            $originalJob = ScheduledJob::find($request->get('copy'));
            if ($originalJob) {
                $scheduledJob = $this->createDummyJob();
                $scheduledJob->name = $originalJob->name . ' (Kopie)';
                $scheduledJob->entity_id = $originalJob->entity_id;
                $scheduledJob->action = $originalJob->action;
                $scheduledJob->scheduled_time = $originalJob->scheduled_time;
                $scheduledJob->weekdays = $originalJob->weekdays;
                $scheduledJob->parameters = $originalJob->parameters;
                $scheduledJob->is_repeating = $originalJob->is_repeating;
            } else {
                $scheduledJob = $this->createDummyJob();
            }
        } elseif ($request->has('edit')) {
            $scheduledJob = ScheduledJob::find($request->get('edit'));
            if (!$scheduledJob) {
                $scheduledJob = $this->createDummyJob();
            }
        } else {
            $scheduledJob = $this->createDummyJob();
        }

        // Logs-Pagination (verwendet 'logs_page')
        $logs = Logs::orderBy('created_t', 'desc')->paginate(15, ['*'], 'logs_page');

        return view('homeassistant.scheduled-jobs', compact('scheduledJobs', 'scheduledJob', 'entities', 'queueJobs', 'logs'));
    }

    private function parseEntityResponse($entitiesResponse)
    {
        $entitiesData = json_decode($entitiesResponse->getContent(), true);
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
}
