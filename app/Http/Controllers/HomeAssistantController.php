<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HomeAssistantController extends Controller
{
    private $haUrl;
    private $haToken;

    public function __construct()
    {
        // Setze deine Home Assistant URL und Token in .env
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
     * Alle Switches auflisten
     */
    public function listSwitches()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->haToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->haUrl}/api/states");

            if ($response->successful()) {
                $allStates = $response->json();
                $switches = array_filter($allStates, function($entity) {
                    return str_starts_with($entity['entity_id'], 'switch.');
                });

                return response()->json([
                    'success' => true,
                    'switches' => array_values($switches)
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to get switches'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
