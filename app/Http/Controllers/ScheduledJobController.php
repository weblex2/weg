<?php

namespace App\Http\Controllers;

use App\Models\ScheduledJob;
use App\Jobs\ExecuteHomeAssistantAction;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScheduledJobController extends Controller
{
    public function index()
    {
        $scheduledJobs = ScheduledJob::orderBy('next_run_at')->paginate(15);

        // Für das Formular den ersten Job nehmen
        $scheduledJob = $scheduledJobs->first();

        if (!$scheduledJob) {
            // Dummy-Job erzeugen mit temporärer ID
            $scheduledJob = new ScheduledJob([
                'entity_id'      => '',
                'action'         => '',
                'scheduled_time' => now(),
                'weekdays'       => [],
                'is_repeating'   => false,
                'parameters'     => [],
                'is_active'      => false,
            ]);

            $scheduledJob->id = 0;     // Dummy-ID für Route
            $scheduledJob->exists = true; // existiert, damit Blade Update-Route baut
        }

        return view('homeassistant.scheduled-jobs', compact('scheduledJobs', 'scheduledJob'));
    }

    public function create()
    {
        return view('scheduled-jobs.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
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
                return back()->withErrors(['parameters' => 'Ungültiges JSON-Format'])->withInput();
            }
            $parameters = $decoded;
        }

        $job = ScheduledJob::create([
            'entity_id' => $validated['entity_id'],
            'action' => $validated['action'],
            'parameters' => $parameters,
            'scheduled_time' => $validated['scheduled_time'],
            'weekdays' => $validated['weekdays'] ?? null,
            'is_repeating' => $request->boolean('is_repeating'),
            'is_active' => true,
        ]);

        // Calculate and schedule next run
        $this->scheduleJob($job);

        return redirect()->route('scheduled-jobs.index')
            ->with('success', 'Aktion erfolgreich erstellt!');
    }

    public function edit(ScheduledJob $scheduledJob)
    {
        return view('scheduled-jobs.edit', compact('scheduledJob'));
    }

    public function update(Request $request, ScheduledJob $scheduledJob)
    {
        $validated = $request->validate([
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
        if (isset($validated['parameters_json'])) {
            if (!empty($validated['parameters_json'])) {
                $decoded = json_decode($validated['parameters_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['parameters' => 'Ungültiges JSON-Format'])->withInput();
                }
                $parameters = $decoded;
            }
        }

        $scheduledJob->update([
            'entity_id' => $validated['entity_id'],
            'action' => $validated['action'],
            'parameters' => $parameters,
            'scheduled_time' => $validated['scheduled_time'],
            'weekdays' => $validated['weekdays'] ?? null,
            'is_repeating' => $request->boolean('is_repeating'),
        ]);

        // Reschedule if active
        if ($scheduledJob->is_active) {
            $this->scheduleJob($scheduledJob);
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
            $this->scheduleJob($scheduledJob);
        }

        return back()->with('success', 'Status erfolgreich geändert!');
    }

    private function scheduleJob(ScheduledJob $job)
    {
        $scheduledTime = Carbon::parse($job->scheduled_time);
        $nextRun = Carbon::today()->setTimeFrom($scheduledTime);

        if ($nextRun->isPast()) {
            $nextRun->addDay();
        }

        // Check weekdays
        if (!empty($job->weekdays)) {
            $maxDays = 7;
            $daysChecked = 0;

            while ($daysChecked < $maxDays && !in_array($nextRun->dayOfWeek, $job->weekdays)) {
                $nextRun->addDay();
                $daysChecked++;
            }
        }

        $job->update(['next_run_at' => $nextRun]);

        $delay = $nextRun->diffInSeconds(now());
        ExecuteHomeAssistantAction::dispatch($job)->delay($delay);
    }
}
