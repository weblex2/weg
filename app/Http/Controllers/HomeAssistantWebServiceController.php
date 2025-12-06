<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use WebSocket\Client;
use Illuminate\Support\Facades\Log;

class HomeAssistantWebServiceController extends Controller
{
    private $wsUrl;
    private $token;

    public function __construct()
    {
        // Home Assistant WebSocket URL und Token aus .env
        $this->wsUrl = env('HA_WS_URL', 'ws://192.168.178.71:8123/api/websocket');
        $this->token = env('HA_TOKEN');
    }

    public function listDevices()
    {
        try {
            // WebSocket Verbindung aufbauen
            $client = new Client($this->wsUrl);

            // Auth-Nachricht von Home Assistant empfangen
            $authRequired = json_decode($client->receive());

            if ($authRequired->type === 'auth_required') {
                // Authentifizierung senden
                $client->send(json_encode([
                    'type' => 'auth',
                    'access_token' => $this->token
                ]));

                $authResult = json_decode($client->receive());

                if ($authResult->type !== 'auth_ok') {
                    return response()->json([
                        'error' => 'Authentifizierung fehlgeschlagen'
                    ], 401);
                }
            }

            // Geräteliste anfordern
            $messageId = 1;
            $client->send(json_encode([
                'id' => $messageId,
                'type' => 'config/device_registry/list'
            ]));

            // Antwort empfangen
            $response = json_decode($client->receive());

            // WebSocket schließen
            $client->close();

            if ($response->success && isset($response->result)) {
                $devices = collect($response->result)->map(function($device) {
                    return [
                        'id' => $device->id ?? null,
                        'name' => $device->name ?? 'Unbenannt',
                        'manufacturer' => $device->manufacturer ?? null,
                        'model' => $device->model ?? null,
                        'area_id' => $device->area_id ?? null,
                        'identifiers' => $device->identifiers ?? [],
                    ];
                });

                return response()->json([
                    'success' => true,
                    'devices' => $devices,
                    'count' => $devices->count()
                ]);
            }

            return response()->json([
                'error' => 'Keine Geräte gefunden',
                'response' => $response
            ], 404);

        } catch (\Exception $e) {
            Log::error('Home Assistant WebSocket Fehler: ' . $e->getMessage());

            return response()->json([
                'error' => 'Verbindungsfehler',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Alternative Methode um alle Entities zu holen
    public function listEntities()
    {
        try {
            $client = new Client($this->wsUrl);

            // Auth
            $authRequired = json_decode($client->receive());

            if ($authRequired->type === 'auth_required') {
                $client->send(json_encode([
                    'type' => 'auth',
                    'access_token' => $this->token
                ]));

                $authResult = json_decode($client->receive());

                if ($authResult->type !== 'auth_ok') {
                    return response()->json(['error' => 'Auth failed'], 401);
                }
            }

            // States abrufen
            $messageId = 1;
            $client->send(json_encode([
                'id' => $messageId,
                'type' => 'get_states'
            ]));

            $response = json_decode($client->receive());
            $client->close();

            if ($response->success && isset($response->result)) {
                $entities = collect($response->result)->map(function($entity) {
                    return [
                        'entity_id' => $entity->entity_id,
                        'state' => $entity->state,
                        'name' => $entity->attributes->friendly_name ?? $entity->entity_id,
                        'device_class' => $entity->attributes->device_class ?? null,
                    ];
                });

                return response()->json([
                    'success' => true,
                    'entities' => $entities,
                    'count' => $entities->count()
                ]);
            }

            return response()->json(['error' => 'Keine Entities gefunden'], 404);

        } catch (\Exception $e) {
            Log::error('Home Assistant WebSocket Fehler: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // Service aufrufen (z.B. Gerät ein-/ausschalten)
    public function callService(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',      // z.B. 'switch', 'light'
            'service' => 'required|string',     // z.B. 'turn_on', 'turn_off'
            'entity_id' => 'required|string',   // z.B. 'switch.steckdose_5'
            'service_data' => 'sometimes|array' // Optionale zusätzliche Daten
        ]);

        try {
            $client = new Client($this->wsUrl);

            // Auth
            $authRequired = json_decode($client->receive());

            if ($authRequired->type === 'auth_required') {
                $client->send(json_encode([
                    'type' => 'auth',
                    'access_token' => $this->token
                ]));

                $authResult = json_decode($client->receive());

                if ($authResult->type !== 'auth_ok') {
                    return response()->json(['error' => 'Auth failed'], 401);
                }
            }

            // Service Call senden
            $messageId = 1;
            $serviceData = array_merge(
                ['entity_id' => $request->entity_id],
                $request->service_data ?? []
            );

            $client->send(json_encode([
                'id' => $messageId,
                'type' => 'call_service',
                'domain' => $request->domain,
                'service' => $request->service,
                'service_data' => $serviceData
            ]));

            $response = json_decode($client->receive());
            $client->close();

            if (isset($response->success) && $response->success) {
                return response()->json([
                    'success' => true,
                    'message' => "Service {$request->domain}.{$request->service} erfolgreich aufgerufen",
                    'entity_id' => $request->entity_id
                ]);
            }

            return response()->json([
                'error' => 'Service Call fehlgeschlagen',
                'response' => $response
            ], 400);

        } catch (\Exception $e) {
            Log::error('Home Assistant Service Call Fehler: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
