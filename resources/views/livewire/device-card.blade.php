<div class="relative p-6 bg-white border border-gray-200 rounded-lg shadow-md">
    <button wire:click="remove"
        class="absolute text-red-500 transition-opacity opacity-0 top-2 right-2 hover:text-red-700 hover:opacity-100">
        <i class="fas fa-times"></i>
    </button>

    @if ($isLight)
        <livewire:ha.light :entity-id="$entityId" :key="$friendlyName" />
    @elseif($isSwitch)
        <livewire:ha.switches :entity-id="$entityId" :key="$friendlyName" />
    @else
        <div class="text-center">
            <!-- Standard Switch/Toggle -->
            <h3 class="mb-2 text-lg font-bold">{{ $friendlyName }}</h3>
            <p class="mb-4 text-sm text-gray-500">{{ $entityId }}</p>
            <button wire:click="toggle" wire:loading.attr="disabled"
                class="w-full py-3 transition-colors hover:opacity-80">
                @if ($state === 'off')
                    <i class="text-5xl text-gray-400 fas fa-toggle-off"></i>
                @else
                    <i class="text-5xl text-blue-500 fas fa-toggle-on"></i>
                @endif
            </button>

            <p class="mt-2 text-sm">
                Status: <span class="font-semibold">{{ $state }}</span>
            </p>

            <!-- Zusätzliche Attribute anzeigen -->
            @if (!empty($entityAttributes))
                <div class="pt-4 mt-4 text-left border-t border-gray-200">
                    @if (isset($entityAttributes['brightness']))
                        <p class="text-sm text-gray-600">
                            Helligkeit: <span
                                class="font-medium">{{ round(($entityAttributes['brightness'] / 255) * 100) }}%</span>
                        </p>
                    @endif

                    @if (isset($entityAttributes['temperature']))
                        <p class="text-sm text-gray-600">
                            Temperatur: <span class="font-medium">{{ $entityAttributes['temperature'] }}°C</span>
                        </p>
                    @endif

                    @if (isset($entityAttributes['humidity']))
                        <p class="text-sm text-gray-600">
                            Luftfeuchtigkeit: <span class="font-medium">{{ $entityAttributes['humidity'] }}%</span>
                        </p>
                    @endif
                </div>
            @endif
        </div>
    @endif

</div>
