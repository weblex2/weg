<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use WebSocket\Client;
use WebSocket\ConnectionException;
use App\Events\HomeAssistantStateChanged;

class HomeAssistantWebSocketCommand extends Command
{
    protected $signature = 'ha:websocket {--filter=* : Filter entity IDs (z.B. light.*, switch.wohnzimmer)}';
    protected $description = 'Starte Home Assistant WebSocket Listener für Entity State Changes';

    protected $client;
    protected $messageId = 1;
    protected $haUrl;
    protected $haToken;
    protected $lastPingTime;
    protected $lastReceiveTime;
    protected $reconnectAttempts = 0;
    protected $maxReconnectAttempts = 5;
    protected $pingInterval = 30;
    protected $receiveTimeout = 60; // Wenn 60s nichts empfangen, reconnect

    public function handle()
    {
        $filters = $this->option('filter');

        $this->haUrl = env('HA_URL', 'ws://192.168.178.71:8123');
        $this->haToken = env('HA_TOKEN');

        if (!$this->haToken) {
            $this->error('HOME_ASSISTANT_TOKEN nicht in .env gesetzt!');
            return 1;
        }

        $this->info(date('Y-m-d H:i:s') . ' - Starte Home Assistant WebSocket Listener...');

        if (!empty($filters)) {
            $this->info('Filter aktiv: ' . implode(', ', $filters));
        }

        // Hauptloop mit automatischem Reconnect
        while (true) {
            try {
                $this->connect();
                $this->authenticate();
                $this->subscribeToStateChanges();
                $this->listen($filters);
            } catch (\Exception $e) {
                $this->error('Fehler: ' . $e->getMessage());
                Log::error('WebSocket Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
                    $this->error('Max Reconnect-Versuche erreicht. Beende...');
                    return 1;
                }

                $this->handleReconnect();
            }
        }

        return 0;
    }

    protected function connect()
    {
        // Alte Verbindung schließen falls vorhanden
        if ($this->client) {
            try {
                $this->client->close();
            } catch (\Exception $e) {
                // Ignorieren
            }
        }

        $wsUrl = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $this->haUrl);
        $wsUrl = rtrim($wsUrl, '/') . '/api/websocket';

        $this->info("Verbinde zu: {$wsUrl}");

        $this->client = new Client($wsUrl, [
            'timeout' => 5,  // Kurzer Timeout für non-blocking receive
            'fragment_size' => 4096,
            'persistent' => false, // Nicht persistent, um saubere Reconnects zu ermöglichen
        ]);

        // Erste Nachricht empfangen (auth_required)
        $response = $this->receive(10); // 10s Timeout für auth_required

        if (!$response || $response['type'] !== 'auth_required') {
            throw new \Exception('Unerwartete Antwort: ' . json_encode($response));
        }

        $this->info('✓ Verbindung hergestellt');
        $this->lastPingTime = time();
        $this->lastReceiveTime = time();
        $this->reconnectAttempts = 0;
    }

    protected function authenticate()
    {
        $this->send([
            'type' => 'auth',
            'access_token' => $this->haToken
        ]);

        $response = $this->receive(10);

        if (!$response || $response['type'] !== 'auth_ok') {
            throw new \Exception('Authentifizierung fehlgeschlagen: ' . json_encode($response));
        }

        $this->info('✓ Authentifizierung erfolgreich');
    }

    protected function subscribeToStateChanges()
    {
        $this->send([
            'id' => $this->messageId++,
            'type' => 'subscribe_events',
            'event_type' => 'state_changed'
        ]);

        $response = $this->receive(10);

        if (!$response || !isset($response['success']) || !$response['success']) {
            throw new \Exception('Subscription fehlgeschlagen: ' . json_encode($response));
        }

        $this->info('✓ State Changes abonniert');
        $this->info('Warte auf Events...');
        $this->line('');
    }

    protected function listen(array $filters)
    {
        while (true) {
            // Connection Health Check
            $now = time();

            // Ping senden wenn nötig
            if ($now - $this->lastPingTime > $this->pingInterval) {
                $this->sendPing();
                $this->lastPingTime = $now;
            }

            // Prüfe ob Verbindung tot ist (nichts empfangen seit receiveTimeout)
            if ($now - $this->lastReceiveTime > $this->receiveTimeout) {
                throw new \Exception('Keine Daten empfangen seit ' . $this->receiveTimeout . 's - Verbindung tot');
            }

            try {
                // Receive mit kurzem Timeout (non-blocking)
                $message = $this->receive(1);

                if ($message === null) {
                    // Timeout ist OK, einfach weitermachen
                    continue;
                }

                $this->lastReceiveTime = $now;

                // Pong-Response
                if ($message['type'] === 'pong') {
                    $this->line('<fg=gray>[' . now()->format('H:i:s') . '] Pong empfangen</>');
                    continue;
                }

                // Events verarbeiten
                if ($message['type'] === 'event' && isset($message['event'])) {
                    $event = $message['event'];

                    if (isset($event['data']['entity_id'])) {
                        $this->handleStateChange($event['data'], $filters);
                    }
                }

            } catch (ConnectionException $e) {
                throw new \Exception('Connection Exception: ' . $e->getMessage());
            }
        }
    }

    protected function handleStateChange(array $data, array $filters)
    {
        $entityId = $data['entity_id'];
        $oldState = $data['old_state']['state'] ?? 'unknown';
        $newState = $data['new_state']['state'] ?? 'unknown';

        // Filter anwenden
        if (!empty($filters) && !$this->matchesFilter($entityId, $filters)) {
            return;
        }

        // Ignoriere wenn sich nur Metadaten geändert haben
        if ($oldState === $newState) {
            $oldAttrs = $data['old_state']['attributes'] ?? [];
            $newAttrs = $data['new_state']['attributes'] ?? [];

            if ($this->attributesUnchanged($oldAttrs, $newAttrs)) {
                return;
            }

            $this->line(sprintf(
                '<fg=cyan>[%s]</> <fg=yellow>%s</> <fg=magenta>(Attribute geändert)</>',
                now()->format('H:i:s'),
                $entityId
            ));
            return;
        }

        // State Change ausgeben
        $this->line(sprintf(
            '<fg=cyan>[%s]</> <fg=yellow>%s</>: <fg=red>%s</> → <fg=green>%s</>',
            now()->format('H:i:s'),
            $entityId,
            $oldState,
            $newState
        ));

        // Attributes anzeigen
        $attributes = $data['new_state']['attributes'] ?? [];
        if (!empty($attributes) && isset($attributes['friendly_name'])) {
            $this->line('  └─ ' . $attributes['friendly_name']);
        }

        // Im Cache speichern
        Cache::put("ha_state:{$entityId}", $data['new_state'], now()->addMinutes(5));

        // Events speichern
        $this->storeEvent($entityId, $oldState, $newState, $data['new_state']);

        // LIVE-UPDATE: Event an Frontend senden
        broadcast(new HomeAssistantStateChanged([
            'entity_id' => $entityId,
            'old_state' => $oldState,
            'new_state' => $newState,
            'attributes' => $data['new_state']['attributes'] ?? [],
            'timestamp' => now()->toIso8601String()
        ]))->toOthers();

        // Custom Actions
        $this->handleCustomActions($entityId, $oldState, $newState, $data['new_state']);
    }

    protected function matchesFilter(string $entityId, array $filters): bool
    {
        foreach ($filters as $filter) {
            $pattern = str_replace('*', '.*', $filter);
            if (preg_match('/^' . $pattern . '$/', $entityId)) {
                return true;
            }
        }
        return false;
    }

    protected function attributesUnchanged(array $oldAttrs, array $newAttrs): bool
    {
        $importantKeys = [
            'brightness',
            'temperature',
            'humidity',
            'battery',
            'current_temperature',
            'target_temperature',
            'hvac_action',
            'position',
            'volume_level',
            'media_title',
            'rgb_color',
        ];

        foreach ($importantKeys as $key) {
            $oldVal = $oldAttrs[$key] ?? null;
            $newVal = $newAttrs[$key] ?? null;

            if ($oldVal !== $newVal) {
                return false;
            }
        }

        return true;
    }

    protected function storeEvent(string $entityId, string $oldState, string $newState, array $fullState)
    {
        $events = Cache::get('ha_websocket_events', []);
        $eventId = microtime(true);

        $events[$eventId] = [
            'entity_id' => $entityId,
            'old_state' => $oldState,
            'new_state' => $newState,
            'attributes' => $fullState['attributes'] ?? [],
            'timestamp' => now()->toIso8601String()
        ];

        if (count($events) > 50) {
            $events = array_slice($events, -50, 50, true);
        }

        Cache::put('ha_websocket_events', $events, now()->addMinutes(5));
    }

    protected function handleCustomActions(string $entityId, string $oldState, string $newState, array $fullState)
    {
        if (str_starts_with($entityId, 'light.') && $newState === 'on') {
            Log::info("Licht eingeschaltet: {$entityId}");
        }

        if (str_starts_with($entityId, 'binary_sensor.') && $newState === 'on') {
            Log::warning("Sensor aktiviert: {$entityId}");
        }

        if (str_starts_with($entityId, 'sensor.') && isset($fullState['attributes']['unit_of_measurement'])) {
            if ($fullState['attributes']['unit_of_measurement'] === '°C') {
                $temp = (float) $newState;
                if ($temp > 25) {
                    Log::warning("Hohe Temperatur: {$entityId} = {$temp}°C");
                }
            }
        }
    }

    protected function send(array $data)
    {
        $json = json_encode($data);
        try {
            $this->client->send($json);
        } catch (\Exception $e) {
            throw new \Exception('Send failed: ' . $e->getMessage());
        }
    }

    /**
     * Nachricht empfangen mit Timeout
     * @return array|null
     */
    protected function receive(?int $timeout = null): ?array
    {
        try {
            $message = $this->client->receive();

            if (empty($message)) {
                return null;
            }

            return json_decode($message, true);
        } catch (\WebSocket\TimeoutException $e) {
            // Timeout ist OK - return null
            return null;
        } catch (\Exception $e) {
            // Andere Fehler werfen
            throw $e;
        }
    }

    protected function sendPing()
    {
        try {
            $this->send([
                'id' => $this->messageId++,
                'type' => 'ping'
            ]);
            $this->line('<fg=gray>[' . now()->format('H:i:s') . '] Ping gesendet</>');
        } catch (\Exception $e) {
            throw new \Exception('Ping failed: ' . $e->getMessage());
        }
    }

    protected function handleReconnect()
    {
        $this->reconnectAttempts++;
        $waitTime = min(5 * $this->reconnectAttempts, 30);

        $this->warn("Reconnect-Versuch #{$this->reconnectAttempts} in {$waitTime} Sekunden...");
        sleep($waitTime);

        // messageId zurücksetzen für neue Verbindung
        $this->messageId = 1;
    }
}
