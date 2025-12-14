<?php

// 1. Event erstellen: app/Events/EntityStateChanged.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntityStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $entityId;
    public $state;

    public function __construct(string $entityId, array $state)
    {
        $this->entityId = $entityId;
        $this->state = $state;
    }

    public function broadcastOn()
    {
        return new Channel('homeassistant');
    }

    public function broadcastAs()
    {
        return 'state-changed';
    }
}

// ============================================================================

// 2. Im WebSocket Command das Event dispatchen

protected function handleStateChange(string $entityId, string $oldState, string $newState, array $fullState)
{
    // ... existing code ...

    // Broadcast für Real-time Updates
    broadcast(new EntityStateChanged($entityId, $fullState))->toOthers();
}

// ============================================================================

// 3. Im Livewire DeviceCard Component Listener hinzufügen

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;

class DeviceCard extends Component
{
    public $entityId;
    public $entity = null;
    public $state;
    // ... rest of properties

    protected $listeners = ['echo:homeassistant,state-changed' => 'onStateChanged'];

    public function onStateChanged($event)
    {
        // Prüfe ob es unsere Entity ist
        if ($event['entityId'] === $this->entityId) {
            $this->entity = $event['state'];
            $this->state = $event['state']['state'];

            if ($this->isLight && $this->state === 'on') {
                $this->brightness = $this->entity['attributes']['brightness'] ?? 128;
                $this->colorTemp = $this->entity['attributes']['color_temp_kelvin'] ?? 4000;

                if (isset($this->entity['attributes']['rgb_color'])) {
                    $rgb = $this->entity['attributes']['rgb_color'];
                    $this->rgbColor = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                }
            }
        }
    }

    // ... rest of code
}

// ============================================================================

// 4. ALTERNATIVE: Polling via Cache (ohne Laravel Echo)

// Im DeviceCard Component:
public function mount()
{
    $this->loadEntityState();
    $this->isLight = str_starts_with($this->entityId, 'light.');

    // Starte Polling alle 2 Sekunden
    $this->dispatch('startPolling');
}

// In der device-card.blade.php:
/*
@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        @this.on('startPolling', () => {
            setInterval(() => {
                // Lade State aus Cache
                fetch('/api/ha/state/' + @js($entityId))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            @this.call('updateFromCache', data.state);
                        }
                    });
            }, 2000);
        });
    });
</script>
@endpush
*/

// API Route für Cache-Abfrage:
// routes/api.php
Route::get('/ha/state/{entityId}', function($entityId) {
    $state = Cache::get("ha_state:{$entityId}");

    return response()->json([
        'success' => $state !== null,
        'state' => $state
    ]);
});

// Neue Method im DeviceCard Component:
public function updateFromCache($state)
{
    $this->entity = $state;
    $this->state = $state['state'];

    if ($this->isLight && $this->state === 'on') {
        $this->brightness = $state['attributes']['brightness'] ?? 128;
        $this->colorTemp = $state['attributes']['color_temp_kelvin'] ?? 4000;

        if (isset($state['attributes']['rgb_color'])) {
            $rgb = $state['attributes']['rgb_color'];
            $this->rgbColor = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
        }
    }
}
