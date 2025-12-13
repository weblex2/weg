<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Dashboard;

class HomeAssistantController extends Controller
{
    private $haUrl;
    private $haToken;

    public function __construct()
    {
        $this->haUrl = env('HA_URL', 'http://192.168.178.71:8123');
        $this->haToken = env('HA_TOKEN');
    }

    public function monitor(){
        return view('homeassistant.monitor');
    }
    /**
     * Ermittle die Domain aus der Entity ID
     */
    private function getDomain($entityId)
    {
        $parts = explode('.', $entityId);
        return $parts[0] ?? 'switch';
    }

    /**
     * Entity einschalten
     */
    public function turnOn($entityId)
    {
        $domain = $this->getDomain($entityId);
        return $this->callService('turn_on', $entityId, $domain);
    }

    /**
     * Entity ausschalten
     */
    public function turnOff($entityId)
    {
        $domain = $this->getDomain($entityId);
        return $this->callService('turn_off', $entityId, $domain);
    }

    /**
     * Entity togglen (umschalten)
     */
    public function toggle($entityId)
    {
        $domain = $this->getDomain($entityId);
        return $this->callService('toggle', $entityId, $domain);
    }

    /**
     * Helligkeit setzen (für Lichter)
     */
    public function setBrightness(Request $request, $entityId)
    {
        $validated = $request->validate([
            'brightness' => 'required|integer|min:1|max:255'
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/services/light/turn_on", [
                'entity_id' => $entityId,
                'brightness' => $validated['brightness']
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Brightness set successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to set brightness'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Farbtemperatur setzen (für Lichter)
     */
    public function setColorTemp(Request $request, $entityId)
    {
        $validated = $request->validate([
            'kelvin' => 'required|integer|min:2000|max:6535'
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/services/light/turn_on", [
                'entity_id' => $entityId,
                'color_temp_kelvin' => $validated['kelvin']
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Color temperature set successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to set color temperature'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * RGB Farbe setzen (für Lichter)
     */
    public function setColor(Request $request, $entityId)
    {
        $validated = $request->validate([
            'rgb_color' => 'required|array|size:3',
            'rgb_color.*' => 'integer|min:0|max:255'
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/services/light/turn_on", [
                'entity_id' => $entityId,
                'rgb_color' => $validated['rgb_color']
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Color set successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to set color'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Status einer Entity abrufen
     */
    public function getState($entityId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->haUrl}/api/states/{$entityId}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'state' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to get state'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Service aufrufen (Haupt-Funktion)
     */
    private function callService($service, $entityId, $domain = null)
    {
        try {
            // Wenn keine Domain übergeben wurde, aus entity_id extrahieren
            if ($domain === null) {
                $domain = $this->getDomain($entityId);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/services/{$domain}/{$service}", [
                'entity_id' => $entityId
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => ucfirst($service) . ' executed successfully',
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to execute service',
                'status' => $response->status(),
                'body' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Websocket API Call über HTTP
     */
    private function callWebsocketApi($type)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/websocket", [
                'type' => $type
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            \Log::error("Websocket API Error ({$type}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Hole Areas direkt über Template
     */
    private function getAreasViaTemplate()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/template", [
                'template' => '{{ areas() | list }}'
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return is_array($result) ? $result : [];
            }

            return [];
        } catch (\Exception $e) {
            \Log::error("Template API Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Hole Area für eine bestimmte Entity über Template
     */
    private function getEntityArea($entityId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/template", [
                'template' => "{{ area_name('{$entityId}') }}"
            ]);

            if ($response->successful()) {
                $area = $response->body();
                // Entferne Anführungszeichen falls vorhanden
                $area = trim($area, '"\'');
                return $area === 'None' || $area === 'unknown' || empty($area) ? null : $area;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Alle Entities auflisten mit Area-Informationen
     */
   public function listEntities($type = 'all')
{
    try {
        \Log::channel('database')->info('HA: listEntities - Start', [
            'type' => $type,
            'haUrl' => $this->haUrl ?? 'NOT SET',
            'haToken_set' => isset($this->haToken),
            'haToken_length' => isset($this->haToken) ? strlen($this->haToken) : 0,
        ]);

        // Hole alle States
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->haToken,
            'Content-Type' => 'application/json',
        ])->get("{$this->haUrl}/api/states");

        \Log::channel('database')->info('HA: listEntities - Response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => substr($response->body(), 0, 500),
        ]);

        if (!$response->successful()) {
            \Log::channel('database')->error('HA: listEntities - Request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get entities'
            ], $response->status());
        }

        $allStates = $response->json();

        \Log::channel('database')->info('HA: listEntities - States geladen', [
            'count' => count($allStates),
        ]);

        // Filtere Entities
        if ($type === 'all' || empty($type)) {
            $entities = $allStates;
        } else {
            $entities = array_filter($allStates, function($entity) use ($type) {
                return str_starts_with($entity['entity_id'], $type . '.');
            });
        }

        // Sammle einzigartige Area-Namen
        $uniqueAreas = [];
        $areaDeviceCount = [];

        // Hole Area für jede Entity über Template API
        foreach ($entities as &$entity) {
            $entityId = $entity['entity_id'];
            $areaName = $this->getEntityArea($entityId);

            $entity['area_name'] = $areaName;

            // Sammle Area-Namen für Filter
            if ($areaName && !in_array($areaName, $uniqueAreas)) {
                $uniqueAreas[] = $areaName;
                $areaDeviceCount[$areaName] = 0;
            }

            // Zähle Geräte pro Area
            if ($areaName) {
                $areaDeviceCount[$areaName]++;
            }
        }

        // Sortiere Areas alphabetisch
        sort($uniqueAreas);

        // Erstelle Areas-Array mit Gerätezahl
        $areasWithCount = [];
        foreach ($uniqueAreas as $area) {
            $areasWithCount[] = [
                'name' => $area,
                'device_count' => $areaDeviceCount[$area] ?? 0
            ];
        }

        \Log::channel('database')->info('HA: listEntities - Erfolg', [
            'entity_count' => count($entities),
            'area_count' => count($uniqueAreas),
        ]);

        return response()->json([
            'success' => true,
            'entities' => array_values($entities),
            'areas' => $uniqueAreas,
            'areas_with_count' => $areasWithCount,
            'count' => count($entities)
        ]);

    } catch (\Exception $e) {
        \Log::channel('database')->error('HA: listEntities - Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Dashboard anzeigen
     */
    public function dashboard()
    {
        $response = $this->listEntities('all');
        $responseData = json_decode($response->getContent(), true);

        if ($responseData['success']) {
            $switches = $responseData['entities'];
            $areas = $responseData['areas'] ?? [];
            $areasWithCount = $responseData['areas_with_count'] ?? [];
        } else {
            $switches = [];
            $areas = [];
            $areasWithCount = [];
        }

        // Lade gespeichertes Dashboard
        $savedDashboard = Dashboard::where('is_active', true)->first();

        return view('homeassistant.dashboard', compact('switches', 'savedDashboard', 'areas', 'areasWithCount'));
    }

    /**
     * Dashboard speichern
     */
    public function saveDashboard(Request $request)
    {
        $validated = $request->validate([
            'layout' => 'required|array',
            'name' => 'string|max:255'
        ]);

        // Hole oder erstelle das aktive Dashboard
        $dashboard = Dashboard::where('is_active', true)->first();

        if (!$dashboard) {
            $dashboard = new Dashboard();
            $dashboard->is_active = true;
        }

        $dashboard->name = $validated['name'] ?? 'Mein Dashboard';
        $dashboard->layout = $validated['layout'];
        $dashboard->save();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard gespeichert',
            'dashboard' => $dashboard
        ]);
    }

    /**
     * Dashboard laden
     */
    public function loadDashboard()
    {
        $dashboard = Dashboard::where('is_active', true)->first();

        if ($dashboard) {
            return response()->json([
                'success' => true,
                'dashboard' => $dashboard
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Kein Dashboard gefunden'
        ]);
    }
}
