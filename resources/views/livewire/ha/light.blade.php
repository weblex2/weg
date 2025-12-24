{{-- resources/views/livewire/ha/light.blade.php --}}

<div wire:poll.5s id="dashboard-light-{{ $entityId }}" class="relative p-6 dashboard-switch"
    data-entity-id="light.{{ $entityId }}">
    <button onclick="removeSwitchFromDashboard('dashboard-light-hue_color_spot_4')"
        class="absolute text-red-500 remove-btn top-2 right-2 hover:text-red-700">
        <i class="fas fa-times"></i>
    </button>

    <div class="text-center">
        <h3 class="mb-2 text-lg font-bold">{{ $friendlyName }}</h3>
        <p class="mb-4 text-sm text-gray-500">{{ $entityId }}</p>

        <button wire:click="toggleSwitch"
            class="w-full py-3 mb-4 transition-colors cursor-pointer toggle-btn hover:opacity-80
                {{ $state === 'on' ? 'bg-yellow-100' : 'bg-gray-100' }}">
            <i
                class="text-5xl fas fa-lightbulb
                {{ $state === 'on' ? 'text-yellow-500' : 'text-gray-400' }}">
            </i>
        </button>



        <p class="mb-3 text-sm">Status: <span class="font-semibold status-text">on</span></p>

        <!-- Helligkeitsregler -->
        <div class="mb-3 brightness-control" style="display: block">
            <label class="block mb-1 text-xs text-gray-600">
                <i class="fas fa-sun"></i> Helligkeit: <span class="brightness-value">48</span>%
            </label>

            <input type="range" min="1" max="255" wire:change="setBrightness($event.target.value)"
                value="{{ $brightness }}">

        </div>

        <!-- Farbtemperatur -->
        <div class="mb-3 color-temp-control" style="display: block">
            <label class="block mb-1 text-xs text-gray-600">
                <i class="fas fa-temperature-half"></i> Farbtemperatur: <span class="color-temp-value">2890</span>K
            </label>
            <input type="range" min="2000" max="6535" wire:change="setColorTemp($event.target.value)"
                value="{{ $colorTemp }}">
        </div>

        <!-- RGB Farbauswahl -->
        <div class="w-full h-10 rounded cursor-pointer color-picker" style="display: block">

            <label class="block mb-1 text-xs text-gray-600">
                <i class="fas fa-palette"></i> Farbe
            </label>
            <input type="color" class="w-full h-10 rounded cursor-pointer color-picker"
                wire:change="setColor($event.target.value)" value="{{ $rgbColor }}">
        </div>
    </div>
</div>
