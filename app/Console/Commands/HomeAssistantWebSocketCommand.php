<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HomeAssistantWebSocket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeAssistantWebSocketCommand extends Command
{
    protected $signature = 'ha:websocket {--filter=* : Filter entity IDs (z.B. light.*, switch.wohnzimmer)}';
    protected $description = 'Starte Home Assistant WebSocket Listener für Entity State Changes';

    protected $ws;

    public function handle()
    {
        $filters = $this->option('filter');

        $this->info('Starte Home Assistant WebSocket Listener...');

        if (!empty($filters)) {
            $this->info('Filter aktiv: ' . implode(', ', $filters));
        }

        $this->ws = new HomeAssistantWebSocket();

        $this->ws->listen(function ($data) use ($filters) {
            $entityId = $data['entity_id'];
            $oldState = $data['old_state']['state'] ?? 'unknown';
            $newState = $data['new_state']['state'] ?? 'unknown';

            // Filter anwenden
            if (!empty($filters) && !$this->matchesFilter($entityId, $filters)) {
                return;
            }

            // State Change loggen
            $this->line(sprintf(
                '[%s] %s: %s → %s',
                now()->format('H:i:s'),
                $entityId,
                $oldState,
                $newState
            ));

            // Im Cache speichern für Live-Updates
            Cache::put("ha_state:{$entityId}", $data['new_state'], now()->addMinutes(5));

            // Broadcast Event (optional für Laravel Echo/Pusher)
            // broadcast(new EntityStateChanged($entityId, $data['new_state']));

            // Zusätzliche Actions hier
            $this->handleStateChange($entityId, $oldState, $newState, $data['new_state']);
        });
    }

    /**
     * Prüfe ob Entity ID zum Filter passt
     */
    protected function matchesFilter(string $entityId, array $filters): bool
    {
        foreach ($filters as $filter) {
            // Wildcard Support (z.B. light.*)
            $pattern = str_replace('*', '.*', $filter);
            if (preg_match('/^' . $pattern . '$/', $entityId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle State Changes - Hier kannst du Custom Logic implementieren
     */
    protected function handleStateChange(string $entityId, string $oldState, string $newState, array $fullState)
    {
        // Speichere Events im Cache für Live-View
        $events = Cache::get('ha_websocket_events', []);
        $eventId = microtime(true);

        $events[$eventId] = [
            'entity_id' => $entityId,
            'old_state' => [
                'state' => $oldState,
                'attributes' => $fullState['attributes'] ?? []
            ],
            'new_state' => $fullState,
            'timestamp' => now()->toIso8601String()
        ];

        // Nur die letzten 50 Events behalten
        if (count($events) > 50) {
            $events = array_slice($events, -50, 50, true);
        }

        Cache::put('ha_websocket_events', $events, now()->addMinutes(5));

        // Beispiel: Logging bei bestimmten Entities
        if (str_starts_with($entityId, 'light.') && $newState === 'on') {
            Log::info("Licht eingeschaltet: {$entityId}");
        }

        // Beispiel: Benachrichtigung bei Alarm
        if (str_starts_with($entityId, 'binary_sensor.') && $newState === 'on') {
            Log::warning("Sensor aktiviert: {$entityId}");
            // Notification senden...
        }

        // Beispiel: Statistiken sammeln
        // DB::table('entity_history')->insert([
        //     'entity_id' => $entityId,
        //     'old_state' => $oldState,
        //     'new_state' => $newState,
        //     'attributes' => json_encode($fullState['attributes'] ?? []),
        //     'created_at' => now()
        // ]);
    }
}
