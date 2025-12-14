<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Dashboard;
use Illuminate\Support\Facades\Http;

class DashboardManager extends Component
{
    public $switches = [];
    public $areas = [];
    public $areasWithCount = [];
    public $savedDashboard = null;
    public $dashboardLayout = [];

    public $searchQuery = '';
    public $currentEntityType = 'all';
    public $currentArea = 'all';
    public $selectedAreaText = 'Alle Bereiche';

    protected $listeners = ['refreshDashboard' => '$refresh'];

    public function mount()
    {
        $this->loadHomeAssistantData();
        $this->loadSavedDashboard();
    }

    public function loadHomeAssistantData()
    {
        // Hier deine Home Assistant API Logik
        try {
            $haUrl = config('homeassistant.url');
            $haToken = config('homeassistant.token');

            if (!$haUrl || !$haToken) {
                session()->flash('error', 'Home Assistant URL oder Token nicht konfiguriert');
                $this->switches = [];
                return;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $haToken,
                'Content-Type' => 'application/json',
            ])->get($haUrl . '/api/states');

            if (!$response->successful()) {
                session()->flash('error', 'Fehler beim Laden der Home Assistant Daten: ' . $response->status());
                $this->switches = [];
                return;
            }

            $allStates = $response->json();

            // Filter nur relevante Entities
            $this->switches = collect($allStates)->filter(function ($entity) {
                $domain = explode('.', $entity['entity_id'])[0];
                return in_array($domain, [
                    'switch', 'light', 'sensor', 'climate', 'cover',
                    'media_player', 'binary_sensor', 'camera', 'lock', 'fan'
                ]);
            })->map(function ($entity) {
                $entity['area_name'] = $this->getAreaName($entity['entity_id']);
                return $entity;
            })->values()->toArray();

            $this->loadAreas();

        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Laden der Home Assistant Daten: ' . $e->getMessage());
            $this->switches = [];
        }
    }

    public function loadAreas()
    {
        // Lade Bereiche aus Home Assistant
        try {
            $haUrl = config('homeassistant.url');
            $haToken = config('homeassistant.token');

            if (!$haUrl || !$haToken) {
                $this->areas = [];
                $this->areasWithCount = [];
                return;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $haToken,
                'Content-Type' => 'application/json',
            ])->get($haUrl . '/api/config/area_registry/list');

            if ($response->successful()) {
                $this->areas = collect($response->json())->pluck('name')->toArray();

                // Zähle Geräte pro Bereich
                $this->areasWithCount = collect($this->areas)->map(function ($area) {
                    $count = collect($this->switches)->filter(function ($switch) use ($area) {
                        return ($switch['area_name'] ?? '') === $area;
                    })->count();

                    return [
                        'name' => $area,
                        'device_count' => $count
                    ];
                })->toArray();
            } else {
                $this->areas = [];
                $this->areasWithCount = [];
            }
        } catch (\Exception $e) {
            $this->areas = [];
            $this->areasWithCount = [];
        }
    }

    public function getAreaName($entityId)
    {
        // Logik um Area Name für Entity zu bekommen
        // Dies müsste angepasst werden basierend auf deiner HA Konfiguration
        return '';
    }

    public function loadSavedDashboard()
    {
        $this->savedDashboard = Dashboard::where('user_id', auth()->id())
            ->where('name', 'Mein Dashboard')
            ->first();

        if ($this->savedDashboard && $this->savedDashboard->layout) {
            $this->dashboardLayout = $this->savedDashboard->layout;
        }
    }

    public function filterByType($type)
    {
        $this->currentEntityType = $type;
        $this->currentArea = 'all';
        $this->selectedAreaText = 'Alle Bereiche';
    }

    public function filterByArea($area)
    {
        $this->currentArea = $area;
        $this->selectedAreaText = $area === 'all' ? 'Alle Bereiche' : $area;

        if ($this->currentEntityType === 'areas') {
            $this->currentEntityType = 'all';
        }
    }

    public function getFilteredSwitches()
    {
        return collect($this->switches)->filter(function ($switch) {
            $entityId = strtolower($switch['entity_id']);
            $friendlyName = strtolower($switch['attributes']['friendly_name'] ?? $entityId);
            $areaName = strtolower($switch['area_name'] ?? '');
            $searchLower = strtolower($this->searchQuery);

            // Search Filter
            $matchesSearch = empty($searchLower) ||
                str_contains($friendlyName, $searchLower) ||
                str_contains($entityId, $searchLower);

            // Type Filter
            $matchesType = $this->currentEntityType === 'all' ||
                str_starts_with($entityId, $this->currentEntityType . '.');

            // Area Filter
            $matchesArea = $this->currentArea === 'all' ||
                $areaName === strtolower($this->currentArea);

            return $matchesSearch && $matchesType && $matchesArea;
        })->values()->toArray();
    }

    public function addToDashboard($entityId)
    {
        if (!in_array($entityId, $this->dashboardLayout)) {
            $this->dashboardLayout[] = $entityId;
        }
    }

    public function removeFromDashboard($entityId)
    {
        $this->dashboardLayout = array_values(
            array_filter($this->dashboardLayout, fn($id) => $id !== $entityId)
        );
    }

    public function toggleSwitch($entityId)
    {
        try {
            $response = Http::post(
                config('homeassistant.url') . '/api/services/homeassistant/toggle',
                ['entity_id' => $entityId]
            );

            if ($response->successful()) {
                $this->dispatch('switchToggled', entityId: $entityId);
                return ['success' => true];
            }

            return ['success' => false, 'error' => 'Toggle fehlgeschlagen'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getEntityState($entityId)
    {
        try {
            $response = Http::get(config('homeassistant.url') . '/api/states/' . $entityId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'state' => $response->json()
                ];
            }

            return ['success' => false];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function setBrightness($entityId, $brightness)
    {
        try {
            $response = Http::post(
                config('homeassistant.url') . '/api/services/light/turn_on',
                [
                    'entity_id' => $entityId,
                    'brightness' => (int) $brightness
                ]
            );

            return ['success' => $response->successful()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function setColorTemp($entityId, $kelvin)
    {
        try {
            $response = Http::post(
                config('homeassistant.url') . '/api/services/light/turn_on',
                [
                    'entity_id' => $entityId,
                    'color_temp_kelvin' => (int) $kelvin
                ]
            );

            return ['success' => $response->successful()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function setColor($entityId, $rgbColor)
    {
        try {
            $response = Http::post(
                config('homeassistant.url') . '/api/services/light/turn_on',
                [
                    'entity_id' => $entityId,
                    'rgb_color' => $rgbColor
                ]
            );

            return ['success' => $response->successful()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function saveDashboard()
    {
        try {
            Dashboard::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'name' => 'Mein Dashboard'
                ],
                [
                    'layout' => $this->dashboardLayout
                ]
            );

            session()->flash('success', 'Dashboard erfolgreich gespeichert!');
            return ['success' => true];
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Speichern: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function render()
    {
        return view('livewire.dashboard-manager', [
            'filteredSwitches' => $this->getFilteredSwitches(),
            'areas' => $this->areas,
            'areasWithCount' => $this->areasWithCount
        ]);
    }
}
