<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class DeviceCard extends Component
{
    public $entityId;
    public $entity = null;
    public $state;
    public $friendlyName;
    public $isLight = false;

    // Light controls
    public $brightness = 128;
    public $colorTemp = 4000;
    public $rgbColor = '#ffffff';

    public function mount()
    {
        $this->loadEntityState();
        $this->isLight = str_starts_with($this->entityId, 'light.');
    }

    public function loadEntityState()
    {
        try {
            $response = Http::get(config('homeassistant.url') . '/api/states/' . $this->entityId);

            if ($response->successful()) {
                $this->entity = $response->json();
                $this->state = $this->entity['state'];
                $this->friendlyName = $this->entity['attributes']['friendly_name'] ?? $this->entityId;

                // Load light attributes
                if ($this->isLight && $this->state === 'on') {
                    $this->brightness = $this->entity['attributes']['brightness'] ?? 128;
                    $this->colorTemp = $this->entity['attributes']['color_temp_kelvin'] ?? 4000;

                    if (isset($this->entity['attributes']['rgb_color'])) {
                        $rgb = $this->entity['attributes']['rgb_color'];
                        $this->rgbColor = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->state = 'unavailable';
            $this->friendlyName = $this->entityId;
        }
    }

    public function toggle()
    {
        try {
            $response = Http::post(
                config('homeassistant.url') . '/api/services/homeassistant/toggle',
                ['entity_id' => $this->entityId]
            );

            if ($response->successful()) {
                $this->loadEntityState();
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Schalten: ' . $e->getMessage());
        }
    }

    public function setBrightness()
    {
        try {
            Http::post(
                config('homeassistant.url') . '/api/services/light/turn_on',
                [
                    'entity_id' => $this->entityId,
                    'brightness' => (int) $this->brightness
                ]
            );
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Einstellen der Helligkeit');
        }
    }

    public function setColorTemp()
    {
        try {
            Http::post(
                config('homeassistant.url') . '/api/services/light/turn_on',
                [
                    'entity_id' => $this->entityId,
                    'color_temp_kelvin' => (int) $this->colorTemp
                ]
            );
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Einstellen der Farbtemperatur');
        }
    }

    public function setColor()
    {
        try {
            $r = hexdec(substr($this->rgbColor, 1, 2));
            $g = hexdec(substr($this->rgbColor, 3, 2));
            $b = hexdec(substr($this->rgbColor, 5, 2));

            Http::post(
                config('homeassistant.url') . '/api/services/light/turn_on',
                [
                    'entity_id' => $this->entityId,
                    'rgb_color' => [$r, $g, $b]
                ]
            );
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Einstellen der Farbe');
        }
    }

    public function remove()
    {
        $this->dispatch('removeFromDashboard', entityId: $this->entityId)->to(DashboardManager::class);
    }

    public function render()
    {
        return view('livewire.device-card');
    }
}
