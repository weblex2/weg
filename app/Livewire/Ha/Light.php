<?php
// app/Livewire/Ha/Light.php

namespace App\Livewire\Ha;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\HomeAssistantWebServiceController;

class Light extends Component
{
    public $entityId;
    public $friendlyName;
    public $state = 'off';
    public $brightness = 0;
    public $colorTemp = 4000;
    public $rgbColor = '#ffffff';
    public $supportsBrightness = false;
    public $supportsColorTemp = false;
    public $supportsColor = false;
    public $removable = true;

    protected $listeners = ['entityStateChanged' => 'updateState'];

    public function mount($entityId, $state = [])
    {
        $this->entityId = $entityId;
        $this->updateFromState($state);
    }

    public function updateFromState($state)
    {
        if (empty($state)) {
            $state = $this->fetchCurrentState();
        }

        $this->state = $state['state'] ?? 'off';
        $attributes = $state['attributes'] ?? [];

        $this->friendlyName = $attributes['friendly_name'] ?? $this->entityId;

        // Brightness (0-255 -> 0-100%)
        if (isset($attributes['brightness'])) {
            $this->brightness = round(($attributes['brightness'] / 255) * 100);
            $this->supportsBrightness = true;
        }

        // Color Temperature (Mired oder Kelvin)
        if (isset($attributes['color_temp'])) {
            // Mired zu Kelvin: K = 1000000 / mired
            $this->colorTemp = $attributes['color_temp'];
            $this->supportsColorTemp = true;
        } elseif (isset($attributes['color_temp_kelvin'])) {
            $this->colorTemp = $attributes['color_temp_kelvin'];
            $this->supportsColorTemp = true;
        }

        // RGB Color
        if (isset($attributes['rgb_color'])) {
            $rgb = $attributes['rgb_color'];
            $this->rgbColor = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
            $this->supportsColor = true;
        }

        // Supported Features prüfen
        $supportedFeatures = $attributes['supported_features'] ?? 0;
        $this->supportsBrightness = ($supportedFeatures & 1) !== 0; // SUPPORT_BRIGHTNESS = 1
        $this->supportsColorTemp = ($supportedFeatures & 2) !== 0;  // SUPPORT_COLOR_TEMP = 2
        $this->supportsColor = ($supportedFeatures & 16) !== 0;     // SUPPORT_COLOR = 16
    }

    public function fetchCurrentState()
    {
        try {
            $haUrl = Redis::get('settings:ha_url');
            $haToken = Redis::get('settings:ha_token');

            if (!$haUrl || !$haToken) {
                return [];
            }

            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $haToken,
                    'Content-Type' => 'application/json',
                ])
                ->get(rtrim($haUrl, '/') . '/api/states/' . $this->entityId);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            \Log::error('Failed to fetch light state', [
                'entity_id' => $this->entityId,
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }

    public function fetchStateFromHA()
    {
        try {
            $controller = app(\App\Http\Controllers\HomeAssistantWebServiceController::class);

            // Direkt die Entity-Infos via WebSocket holen
            $response = $controller->listEntities();

            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $data = json_decode($response->getContent(), true);
            } elseif (is_array($response)) {
                $data = $response;
            } else {
                $data = [];
            }

            $entity = collect($data['entities'] ?? [])
                ->first(fn($e) => $e['entity_id'] === $this->entityId);

            if ($entity) {
                $this->state = $entity['state'] ?? 'off';
                $attributes = $entity['attributes'] ?? [];

                if (isset($attributes['brightness'])) {
                    $this->brightness = round(($attributes['brightness'] / 255) * 100);
                }

                if (isset($attributes['color_temp'])) {
                    $this->colorTemp = $attributes['color_temp'];
                } elseif (isset($attributes['color_temp_kelvin'])) {
                    $this->colorTemp = $attributes['color_temp_kelvin'];
                }

                if (isset($attributes['rgb_color'])) {
                    $rgb = $attributes['rgb_color'];
                    $this->rgbColor = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('HA fetchStateFromHA failed', [
                'entity_id' => $this->entityId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function toggleSwitch()
    {
        $service = $this->state === 'on' ? 'turn_off' : 'turn_on';
        $this->callService($service);
    }

    public function setBrightness($value)
    {
        $this->brightness = $value;

        // 0-100% -> 0-255
        $brightness = round(($value / 100) * 255);

        $this->callService('turn_on', [
            'brightness' => $brightness
        ]);
    }

    public function setColorTemp($value)
    {
        $this->colorTemp = $value;

        $this->callService('turn_on', [
            'kelvin' => (int) $value
        ]);
    }

    public function setColor($hexColor)
    {
        $this->rgbColor = $hexColor;

        // Hex zu RGB konvertieren
        $hex = ltrim($hexColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $this->callService('turn_on', [
            'rgb_color' => [$r, $g, $b]
        ]);
    }

    protected function callService($service, $data = [])
    {
        try {
            // Controller als Service verwenden
            $controller = new HomeAssistantWebServiceController();

            // Wir bauen ein Fake-Request-Objekt für die Methode
            $request = new \Illuminate\Http\Request();
            $request->replace([
                'domain' => 'light',
                'service' => $service,
                'entity_id' => $this->entityId,
                'service_data' => $data,
            ]);

            // callService aufrufen
            $response = $controller->callService($request);

            // Response prüfen (Controller gibt JSON zurück)
            $content = $response->getData(true);

            if (isset($content['success']) && $content['success']) {
                // Erfolgreich → State aktualisieren
                $this->updateFromState($this->fetchCurrentState());
            } else {
                \Log::error('HA Service Call fehlgeschlagen', [
                    'entity_id' => $this->entityId,
                    'service' => $service,
                    'response' => $content
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Fehler beim Aufruf des HA Controllers', [
                'entity_id' => $this->entityId,
                'service' => $service,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function remove()
    {
        $this->dispatch('removeLightFromDashboard', entityId: $this->entityId);
    }

    public function updateState($data)
    {
        // Wird vom WebSocket Event getriggert
        if ($data['entity_id'] === $this->entityId) {
            $this->updateFromState($data['new_state']);
        }
    }

    public function render()
    {
        return view('livewire.ha.light');
    }
}
