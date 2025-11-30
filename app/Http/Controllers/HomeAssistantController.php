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
        $this->haUrl = env('HA_URL', 'http://homeassistant.local:8123');
        $this->haToken = env('HA_TOKEN');
    }

    /**
     * Steckdose einschalten
     */
    public function turnOn($entityId)
    {
        return $this->callService('turn_on', $entityId);
    }

    /**
     * Steckdose ausschalten
     */
    public function turnOff($entityId)
    {
        return $this->callService('turn_off', $entityId);
    }

    /**
     * Steckdose togglen (umschalten)
     */
    public function toggle($entityId)
    {
        return $this->callService('toggle', $entityId);
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
    private function callService($service, $entityId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->haUrl}/api/services/switch/{$service}", [
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
                'error' => 'Failed to execute service'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alle Areas (Bereiche) abrufen
     */
    public function getAreas()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->haUrl}/api/config/area_registry/list");

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Alle Devices abrufen
     */
    public function getDevices()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->haUrl}/api/config/device_registry/list");

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Alle Entity Registry Einträge abrufen
     */
    public function getEntityRegistry()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->haUrl}/api/config/entity_registry/list");

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Alle Entities auflisten (mit Area-Informationen)
     */
    public function listEntities($type = 'all')
    {
        try {
            // Hole alle States
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->haUrl}/api/states");

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get entities'
                ], $response->status());
            }

            $allStates = $response->json();

            // Hole Area-Informationen
            $areas = $this->getAreas();
            $devices = $this->getDevices();
            $entityRegistry = $this->getEntityRegistry();

            // Erstelle Mappings
            $areaMap = [];
            foreach ($areas as $area) {
                $areaMap[$area['area_id']] = $area['name'];
            }

            $deviceAreaMap = [];
            foreach ($devices as $device) {
                if (isset($device['area_id']) && $device['area_id']) {
                    $deviceAreaMap[$device['id']] = $device['area_id'];
                }
            }

            $entityDeviceMap = [];
            $entityAreaMap = [];
            foreach ($entityRegistry as $entity) {
                // Direkte Area-Zuordnung
                if (isset($entity['area_id']) && $entity['area_id']) {
                    $entityAreaMap[$entity['entity_id']] = $entity['area_id'];
                }
                // Device-Zuordnung
                if (isset($entity['device_id']) && $entity['device_id']) {
                    $entityDeviceMap[$entity['entity_id']] = $entity['device_id'];
                }
            }

            // Filtere Entities
            if ($type === 'all' || empty($type)) {
                $entities = $allStates;
            } else {
                $entities = array_filter($allStates, function($entity) use ($type) {
                    return str_starts_with($entity['entity_id'], $type . '.');
                });
            }

            // Füge Area-Information zu jeder Entity hinzu
            foreach ($entities as &$entity) {
                $entityId = $entity['entity_id'];
                $areaName = null;

                // Prüfe direkte Area-Zuordnung
                if (isset($entityAreaMap[$entityId])) {
                    $areaId = $entityAreaMap[$entityId];
                    $areaName = $areaMap[$areaId] ?? null;
                }
                // Prüfe Area über Device
                elseif (isset($entityDeviceMap[$entityId])) {
                    $deviceId = $entityDeviceMap[$entityId];
                    if (isset($deviceAreaMap[$deviceId])) {
                        $areaId = $deviceAreaMap[$deviceId];
                        $areaName = $areaMap[$areaId] ?? null;
                    }
                }

                $entity['area_name'] = $areaName;
            }

            return response()->json([
                'success' => true,
                'entities' => array_values($entities),
                'count' => count($entities),
                'areas' => array_values($areaMap) // Für Area-Filter
            ]);

        } catch (\Exception $e) {
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
        } else {
            $switches = [];
            $areas = [];
        }

        // Lade gespeichertes Dashboard
        $savedDashboard = Dashboard::where('is_active', true)->first();

        return view('homeassistant.dashboard', compact('switches', 'savedDashboard', 'areas'));
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
