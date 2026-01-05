<?php

namespace App\Livewire\Ha;

use Livewire\Component;
use App\Http\Controllers\HomeAssistantWebServiceController;

class Switches extends Component
{
    public $entityId;
    public $friendlyName;
    public $state = 'off'; // on/off
    public $removable = true;

    protected $listeners = ['entityStateChanged' => 'updateState'];

    public function mount($entityId)
    {
        $this->entityId = $entityId;
        $this->fetchStateFromHA();
    }

    public function fetchStateFromHA()
    {
        try {
            $controller = app(HomeAssistantWebServiceController::class);
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
                $this->friendlyName = $entity['name'] ?? $this->entityId;
            }
        } catch (\Exception $e) {
            \Log::error('HA Switch fetchStateFromHA failed', [
                'entity_id' => $this->entityId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function toggleSwitch()
    {
        $service = $this->state === 'on' ? 'turn_off' : 'turn_on';

        try {
            $controller = app(HomeAssistantWebServiceController::class);

            $requestData = new \Illuminate\Http\Request([
                'domain' => 'switch',
                'service' => $service,
                'entity_id' => $this->entityId,
            ]);

            $controller->callService($requestData);

            // Status sofort updaten
            $this->fetchStateFromHA();
        } catch (\Exception $e) {
            \Log::error('HA Switch toggleSwitch failed', [
                'entity_id' => $this->entityId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function remove()
    {
        $this->dispatch('removeSwitchFromDashboard', entityId: $this->entityId);
    }

    public function updateState(...$params)
    {
        logger()->info('Light updateState - Raw params', ['params' => $params]);

        // Drei separate Parameter: entityId, state, attributes
        if (count($params) >= 3) {
            $entityId = $params[0];
            $state = $params[1];
            $attributes = $params[2];
        } else {
            logger()->error('Light updateState: Unexpected params', ['params' => $params]);
            return;
        }

        logger()->info('Light updateState parsed', [
            'this_entity' => $this->entityId,
            'received_entity' => $entityId,
            'state' => $state,
            'attributes' => $attributes
        ]);

        if ($entityId !== $this->entityId) {
            return;
        }

        logger()->info("✅ Light {$this->entityId} wird aktualisiert zu: {$state}");

        $this->state = $state;

        if (isset($attributes['brightness'])) {
            $this->brightness = $attributes['brightness'];
        }

        if (isset($attributes['friendly_name'])) {
            $this->friendlyName = $attributes['friendly_name'];
        }
    }

    public function render()
    {
        return view('livewire.ha.switches');
    }
}
