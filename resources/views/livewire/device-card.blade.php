<div class="relative p-6 bg-white border border-gray-200 rounded-lg shadow-md">
    <button wire:click="remove"
        class="absolute text-red-500 transition-opacity opacity-0 top-2 right-2 hover:text-red-700 hover:opacity-100">
        <i class="fas fa-times"></i>
    </button>

    <div class="text-center">
        <h3 class="mb-2 text-lg font-bold">{{ $friendlyName }}</h3>
        <p class="mb-4 text-sm text-gray-500">{{ $entityId }}</p>

        @if ($isLight)
            <!-- Licht-Steuerung -->
            <button wire:click="toggle" wire:loading.attr="disabled"
                class="w-full py-3 mb-4 transition-colors hover:opacity-80">
                @if ($state === 'off')
                    <i class="text-5xl text-gray-400 fas fa-lightbulb"></i>
                @else
                    <i class="text-5xl text-yellow-500 fas fa-lightbulb"></i>
                @endif
            </button>

            <p class="mb-3 text-sm">
                Status: <span class="font-semibold">{{ $state }}</span>
            </p>

            @if ($state === 'on')
                <!-- Helligkeitsregler -->
                <div class="mb-3">
                    <label class="block mb-1 text-xs text-gray-600">
                        <i class="fas fa-sun"></i> Helligkeit: {{ round(($brightness / 255) * 100) }}%
                    </label>
                    <input type="range" min="1" max="255" wire:model.live.debounce.500ms="brightness"
                        wire:change="setBrightness" class="w-full">
                </div>

                <!-- Farbtemperatur -->
                <div class="mb-3">
                    <label class="block mb-1 text-xs text-gray-600">
                        <i class="fas fa-temperature-half"></i> Farbtemperatur: {{ $colorTemp }}K
                    </label>
                    <input type="range" min="2000" max="6535" wire:model.live.debounce.500ms="colorTemp"
                        wire:change="setColorTemp" class="w-full">
                </div>

                <!-- RGB Farbauswahl -->
                <div>
                    <label class="block mb-1 text-xs text-gray-600">
                        <i class="fas fa-palette"></i> Farbe
                    </label>
                    <input type="color" wire:model.live.debounce.500ms="rgbColor" wire:change="setColor"
                        class="w-full h-10 border border-gray-300 rounded cursor-pointer">
                </div>
            @endif
        @else
            <!-- Standard Switch/Toggle -->
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
        @endif
    </div>
</div>
