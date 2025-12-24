<div id="dashboard-switch-{{ $entityId }}"
    class="relative p-6 bg-white border border-gray-200 rounded-lg shadow-md dashboard-switch"
    data-entity-id="{{ $entityId }}">

    <button wire:click="remove" class="absolute text-red-500 remove-btn top-2 right-2 hover:text-red-700">
        <i class="fas fa-times"></i>
    </button>

    <div class="text-center">
        <h3 class="mb-2 text-lg font-bold">{{ $friendlyName }}</h3>
        <p class="mb-4 text-sm text-gray-500">{{ $entityId }}</p>

        <button wire:click="toggleSwitch"
            class="w-full py-3 mb-4 transition-colors cursor-pointer toggle-btn hover:opacity-80
                {{ $state === 'on' ? 'bg-yellow-100' : 'bg-gray-100' }}">
            <i class="text-5xl fas fa-plug
                {{ $state === 'on' ? 'text-yellow-500' : 'text-gray-400' }}">
            </i>
        </button>

        <p class="mb-3 text-sm">Status: <span class="font-semibold">{{ $state }}</span></p>
    </div>
</div>
