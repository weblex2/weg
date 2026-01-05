<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DeviceCard extends Component
{
    public $entityId;
    public $state;
    public $entityAttributes = []; // Renamed from $attributes
    public $friendlyName;
    public $isLight = false;
    public $isSwitch = false;

    protected $listeners = ['entityStateChanged'];

    public function mount($entityId)
    {
        $this->entityId = $entityId;
        $this->loadState();
        $this->determineType();
    }

    public function determineType()
    {
        $this->isLight = str_starts_with($this->entityId, 'light.');
        $this->isSwitch = str_starts_with($this->entityId, 'switch.');
    }

    public function loadState()
    {
        // Versuche zuerst aus Cache zu laden
        $cached = Cache::get("ha_state:{$this->entityId}");

        if ($cached) {
            $this->state = $cached['state'] ?? 'unknown';
            $this->entityAttributes = $cached['attributes'] ?? [];
        } else {
            // Fallback: Lade von Home Assistant API
            $this->refreshFromApi();
        }

        $this->friendlyName = $this->entityAttributes['friendly_name'] ?? $this->entityId;
    }

    public function refreshFromApi()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('homeassistant.token'),
            ])->get(config('homeassistant.url') . '/api/states/' . $this->entityId);

            if ($response->successful()) {
                $data = $response->json();
                $this->state = $data['state'];
                $this->entityAttributes = $data['attributes'] ?? [];

                // In Cache speichern
                Cache::put("ha_state:{$this->entityId}", [
                    'state' => $this->state,
                    'attributes' => $this->entityAttributes
                ], now()->addMinutes(5));
            }
        } catch (\Exception $e) {
            // Fehler ignorieren oder loggen
        }
    }

    /**
     * Wird aufgerufen wenn WebSocket State Change empfängt
     */
    public function entityStateChanged(...$params)
    {
        logger()->info('DeviceCard entityStateChanged - Raw params', ['params' => $params]);

        // Drei separate Parameter: entityId, state, attributes
        if (count($params) >= 3) {
            $entityId = $params[0];
            $state = $params[1];
            $attributes = $params[2];
        }
        // Als Array
        elseif (count($params) === 1 && is_array($params[0])) {
            $data = $params[0];
            $entityId = $data['entityId'] ?? $data['entity_id'] ?? null;
            $state = $data['state'] ?? $data['new_state'] ?? null;
            $attributes = $data['attributes'] ?? [];
        } else {
            logger()->error('Unexpected params structure', ['params' => $params]);
            return;
        }

        logger()->info('DeviceCard entityStateChanged parsed', [
            'component_entity' => $this->entityId,
            'received_entity' => $entityId,
            'state' => $state,
            'attributes' => $attributes
        ]);

        if ($entityId !== $this->entityId) {
            logger()->info("Entity ID mismatch: {$entityId} !== {$this->entityId}");
            return;
        }

        logger()->info("✅ Updating {$this->entityId} to state: {$state}");

        // Eigenen State aktualisieren
        $this->state = $state;
        $this->entityAttributes = array_merge($this->entityAttributes, $attributes);
        $this->friendlyName = $this->entityAttributes['friendly_name'] ?? $this->entityId;

        // Event an Kind-Komponenten weiterleiten - als drei Parameter
        if ($this->isLight) {
            $this->dispatch('entityStateChanged',
                $entityId,
                $state,
                $attributes
            )->to('ha.light');
        } elseif ($this->isSwitch) {
            $this->dispatch('entityStateChanged',
                $entityId,
                $state,
                $attributes
            )->to('ha.switches');
        }
    }

    public function toggle()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('homeassistant.token'),
            ])->post(
                config('homeassistant.url') . '/api/services/homeassistant/toggle',
                ['entity_id' => $this->entityId]
            );

            if ($response->successful()) {
                // State wird automatisch via WebSocket aktualisiert
                // Aber als Fallback kurz warten und refreshen
                usleep(100000); // 100ms warten
                $this->refreshFromApi();
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Toggle: ' . $e->getMessage());
        }
    }

    public function remove()
    {
        $this->dispatch('removeFromDashboard', entityId: $this->entityId)->to('dashboard-manager');
    }

    public function render()
    {
        return view('livewire.device-card');
    }
}
