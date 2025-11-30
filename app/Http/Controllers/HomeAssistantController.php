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

            // Hole Area für jede Entity über Template API
            foreach ($entities as &$entity) {
                $entityId = $entity['entity_id'];
                $areaName = $this->getEntityArea($entityId);

                $entity['area_name'] = $areaName;

                // Sammle Area-Namen für Filter
                if ($areaName && !in_array($areaName, $uniqueAreas)) {
                    $uniqueAreas[] = $areaName;
                }
            }

            // Sortiere Areas alphabetisch
            sort($uniqueAreas);

            return response()->json([
                'success' => true,
                'entities' => array_values($entities),
                'areas' => $uniqueAreas,
                'count' => count($entities)
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
            $areas = $responseData['areas'];
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
