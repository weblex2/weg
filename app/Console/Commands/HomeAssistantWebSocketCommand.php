<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use WebSocket\Client;

class HomeAssistantWebSocketCommand extends Command
{
    protected $signature = 'ha:websocket {--filter=* : Filter entity IDs (z.B. light.*, switch.wohnzimmer)}';
    protected $description = 'Starte Home Assistant WebSocket Listener für Entity State Changes';

    protected $client;
    protected $messageId = 1;
    protected $haUrl;
    protected $haToken;
    protected $lastPingTime;
    protected $reconnectAttempts = 0;
    protected $maxReconnectAttempts = 5;

    public function handle()
    {
        $filters = $this->option('filter');

        // Konfiguration aus .env
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

        try {
            $this->connect();
            $this->authenticate();
            $this->subscribeToStateChanges();
            $this->listen($filters);
        } catch (\Exception $e) {
            $this->error('Fehler: ' . $e->getMessage());
            Log::error('WebSocket Error', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }

    /**
     * Verbindung zum WebSocket herstellen
     */
    protected function connect()
    {
        $wsUrl = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $this->haUrl);
        $wsUrl = rtrim($wsUrl, '/') . '/api/websocket';

        $this->info("Verbinde zu: {$wsUrl}");

        $this->client = new Client($wsUrl, [
            'timeout' => 300,  // 5 Minuten Timeout
            'fragment_size' => 4096,
            'persistent' => true,
        ]);

        // Erste Nachricht empfangen (auth_required)
        $response = $this->receive();

        if ($response['type'] !== 'auth_required') {
            throw new \Exception('Unerwartete Antwort: ' . json_encode($response));
        }

        $this->info('✓ Verbindung hergestellt');
        $this->lastPingTime = time();
        $this->reconnectAttempts = 0;
    }

    /**
     * Authentifizierung
     */
    protected function authenticate()
    {
        $this->send([
            'type' => 'auth',
            'access_token' => $this->haToken
        ]);

        $response = $this->receive();

        if ($response['type'] !== 'auth_ok') {
            throw new \Exception('Authentifizierung fehlgeschlagen: ' . json_encode($response));
        }

        $this->info('✓ Authentifizierung erfolgreich');
    }

    /**
     * State Changes abonnieren
     */
    protected function subscribeToStateChanges()
    {
        $this->send([
            'id' => $this->messageId++,
            'type' => 'subscribe_events',
            'event_type' => 'state_changed'
        ]);

        $response = $this->receive();

        if (!isset($response['success']) || !$response['success']) {
            throw new \Exception('Subscription fehlgeschlagen: ' . json_encode($response));
        }

        $this->info('✓ State Changes abonniert');
        $this->info('Warte auf Events...');
        $this->line('');
    }

    /**
     * Event Loop - Empfange und verarbeite Nachrichten
     */
    protected function listen(array $filters)
    {
        while (true) {
            try {
                // Ping senden wenn länger als 30 Sekunden keine Aktivität
                if (time() - $this->lastPingTime > 30) {
                    $this->sendPing();
                    $this->lastPingTime = time();
                }

                $message = $this->receive();
                $this->lastPingTime = time();

                // Pong-Response ignorieren
                if ($message['type'] === 'pong') {
                    continue;
                }

                // Nur Events verarbeiten
                if ($message['type'] === 'event' && isset($message['event'])) {
                    $event = $message['event'];

                    if (isset($event['data']['entity_id'])) {
                        $this->handleStateChange($event['data'], $filters);
                    }
                }

            } catch (\Exception $e) {
                $this->error('Fehler beim Empfangen: ' . $e->getMessage());

                if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
                    $this->error('Max Reconnect-Versuche erreicht. Beende...');
                    return;
                }

                $this->reconnectAttempts++;
                $waitTime = min(5 * $this->reconnectAttempts, 30);

                $this->warn("Versuche Reconnect #{$this->reconnectAttempts} in {$waitTime} Sekunden...");
                sleep($waitTime);

                try {
                    $this->connect();
                    $this->authenticate();
                    $this->subscribeToStateChanges();
                    $this->info('✓ Reconnect erfolgreich!');
                } catch (\Exception $reconnectError) {
                    $this->error('Reconnect fehlgeschlagen: ' . $reconnectError->getMessage());
                }
            }
        }
    }

    /**
     * State Change verarbeiten
     */
    protected function handleStateChange(array $data, array $filters)
    {
        $entityId = $data['entity_id'];
        $oldState = $data['old_state']['state'] ?? 'unknown';
        $newState = $data['new_state']['state'] ?? 'unknown';

        // Filter anwenden
        if (!empty($filters) && !$this->matchesFilter($entityId, $filters)) {
            return;
        }

        // Ignoriere wenn sich nur Metadaten geändert haben, nicht der State
        if ($oldState === $newState) {
            // Optional: Prüfe auch wichtige Attribute
            $oldAttrs = $data['old_state']['attributes'] ?? [];
            $newAttrs = $data['new_state']['attributes'] ?? [];

            // Wenn auch Attribute gleich sind, ignorieren
            if ($this->attributesUnchanged($oldAttrs, $newAttrs)) {
                return;
            }

            // Wenn nur State gleich aber wichtige Attribute geändert, zeige es an
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

        // Attributes anzeigen (optional)
        $attributes = $data['new_state']['attributes'] ?? [];
        if (!empty($attributes) && isset($attributes['friendly_name'])) {
            $this->line('  └─ ' . $attributes['friendly_name']);
        }

        // Im Cache speichern für Live-Updates
        Cache::put("ha_state:{$entityId}", $data['new_state'], now()->addMinutes(5));

        // Events speichern
        $this->storeEvent($entityId, $oldState, $newState, $data['new_state']);

        // Custom Actions
        $this->handleCustomActions($entityId, $oldState, $newState, $data['new_state']);
    }

    /**
     * Prüfe ob Entity ID zum Filter passt
     */
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

    /**
     * Prüfe ob wichtige Attribute sich geändert haben
     */
    protected function attributesUnchanged(array $oldAttrs, array $newAttrs): bool
    {
        // Liste wichtiger Attribute die Änderungen triggern sollten
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
                return false; // Attribute haben sich geändert
            }
        }

        return true; // Keine wichtigen Änderungen
    }

    /**
     * Events im Cache speichern
     */
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

        // Nur die letzten 50 Events behalten
        if (count($events) > 50) {
            $events = array_slice($events, -50, 50, true);
        }

        Cache::put('ha_websocket_events', $events, now()->addMinutes(5));
    }

    /**
     * Custom Actions basierend auf State Changes
     */
    protected function handleCustomActions(string $entityId, string $oldState, string $newState, array $fullState)
    {
        // Beispiel: Logging bei Lichtern
        if (str_starts_with($entityId, 'light.') && $newState === 'on') {
            Log::info("Licht eingeschaltet: {$entityId}");
        }

        // Beispiel: Warnung bei Sensoren
        if (str_starts_with($entityId, 'binary_sensor.') && $newState === 'on') {
            Log::warning("Sensor aktiviert: {$entityId}");
            // Hier Notification senden...
        }

        // Beispiel: Temperatur-Monitoring
        if (str_starts_with($entityId, 'sensor.') && isset($fullState['attributes']['unit_of_measurement'])) {
            if ($fullState['attributes']['unit_of_measurement'] === '°C') {
                $temp = (float) $newState;
                if ($temp > 25) {
                    Log::warning("Hohe Temperatur: {$entityId} = {$temp}°C");
                }
            }
        }
    }

    /**
     * Nachricht senden
     */
    protected function send(array $data)
    {
        $json = json_encode($data);
        $this->client->send($json);
    }

    /**
     * Nachricht empfangen
     */
    protected function receive(): array
    {
        $message = $this->client->receive();
        return json_decode($message, true);
    }

    /**
     * Ping senden um Verbindung am Leben zu halten
     */
    protected function sendPing()
    {
        $this->send([
            'id' => $this->messageId++,
            'type' => 'ping'
        ]);
    }
}
