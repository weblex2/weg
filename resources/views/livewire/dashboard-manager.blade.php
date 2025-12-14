<div>
    <div class="flex h-screen">
        <!-- Linke Sidebar mit verfügbaren Switches -->
        <div class="flex flex-col bg-gray-100 border-r border-gray-300 w-80">
            <div class="p-4 bg-white border-b border-gray-300">
                <h2 class="mb-3 text-xl font-bold text-gray-800">Verfügbare Geräte</h2>

                <!-- Suchfeld -->
                <div class="relative mb-3">
                    <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Geräte suchen..."
                        class="w-full px-3 py-2 pl-10 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="absolute text-gray-400 fas fa-search left-3 top-3"></i>

                    <!-- Entity Type Dropdown Button -->
                    <div class="absolute right-2 top-2" x-data="{ open: false }">
                        <button @click="open = !open"
                            class="px-2 py-1 text-gray-600 hover:text-gray-800 focus:outline-none">
                            <i class="fas fa-filter"></i>
                        </button>

                        <!-- Entity Type Dropdown Menu -->
                        <div x-show="open" @click.away="open = false" x-transition
                            class="absolute right-0 z-10 w-48 mt-2 bg-white border border-gray-300 rounded-lg shadow-lg">
                            <div class="py-1">
                                <button wire:click="filterByType('all')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-list"></i> Alle Typen
                                </button>
                                <button wire:click="filterByType('areas')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-map-marker-alt"></i> Bereiche
                                </button>
                                <div class="my-1 border-t border-gray-200"></div>
                                <button wire:click="filterByType('switch')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-toggle-on"></i> Switches
                                </button>
                                <button wire:click="filterByType('light')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-lightbulb"></i> Lichter
                                </button>
                                <button wire:click="filterByType('sensor')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-gauge"></i> Sensoren
                                </button>
                                <button wire:click="filterByType('climate')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-temperature-half"></i> Klima
                                </button>
                                <button wire:click="filterByType('cover')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-window-maximize"></i> Jalousien
                                </button>
                                <button wire:click="filterByType('media_player')" @click="open = false"
                                    class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                    <i class="mr-2 fas fa-tv"></i> Media Player
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Area Filter Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                        class="w-full px-3 py-2 text-left bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="mr-2 fas fa-map-marker-alt"></i>
                        <span>{{ $selectedAreaText }}</span>
                        <i class="float-right mt-1 fas fa-chevron-down"></i>
                    </button>

                    <!-- Area Dropdown Menu -->
                    <div x-show="open" @click.away="open = false" x-transition
                        class="absolute left-0 right-0 z-10 mt-2 overflow-y-auto bg-white border border-gray-300 rounded-lg shadow-lg max-h-64">
                        <div class="py-1">
                            <button wire:click="filterByArea('all')" @click="open = false"
                                class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                <i class="mr-2 fas fa-list"></i> Alle Bereiche
                            </button>
                            @if (is_array($this->areas) && count($this->areas) > 0)
                                @foreach ($this->areas as $area)
                                    <button wire:click="filterByArea('{{ $area }}')" @click="open = false"
                                        class="w-full px-4 py-2 text-left transition-colors hover:bg-gray-100">
                                        <i class="mr-2 fas fa-map-marker-alt"></i> {{ $area }}
                                    </button>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-4 space-y-3 overflow-y-auto">
                <!-- Bereiche Liste -->
                @if ($currentEntityType === 'areas')
                    <div>
                        @forelse ($areasWithCount as $areaData)
                            <div wire:click="filterByArea('{{ $areaData['name'] }}')"
                                class="p-3 mb-3 transition-shadow bg-white border rounded shadow-sm cursor-pointer hover:shadow-md">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex items-center justify-center flex-shrink-0 w-10 h-10 text-white bg-blue-500 rounded-lg">
                                        <i class="text-xl fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold truncate">{{ $areaData['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $areaData['device_count'] }} Geräte</p>
                                    </div>
                                    <i class="flex-shrink-0 text-gray-400 fas fa-chevron-right"></i>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-gray-400">
                                <i class="mb-2 text-3xl fas fa-map-marker-alt"></i>
                                <p>Keine Bereiche gefunden</p>
                            </div>
                        @endforelse
                    </div>
                @else
                    <!-- Geräte Liste -->
                    <div>
                        @forelse ($filteredSwitches as $switch)
                            <div class="p-3 mb-3 transition-shadow bg-white border rounded shadow-sm cursor-move hover:shadow-md"
                                draggable="true"
                                ondragstart="handleDragStart(event, '{{ $switch['entity_id'] }}', '{{ addslashes($switch['attributes']['friendly_name'] ?? $switch['entity_id']) }}', '{{ $switch['state'] }}')">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-gray-600">
                                        @php
                                            $entityId = $switch['entity_id'];
                                            $icon = 'fa-question-circle';
                                            $iconColor = 'text-gray-600';

                                            if (str_starts_with($entityId, 'switch.')) {
                                                $icon = 'fa-toggle-on';
                                                $iconColor = 'text-blue-600';
                                            } elseif (str_starts_with($entityId, 'light.')) {
                                                $icon = 'fa-lightbulb';
                                                $iconColor = 'text-yellow-500';
                                            } elseif (str_starts_with($entityId, 'sensor.')) {
                                                $icon = 'fa-gauge';
                                                $iconColor = 'text-green-600';
                                            } elseif (str_starts_with($entityId, 'climate.')) {
                                                $icon = 'fa-temperature-half';
                                                $iconColor = 'text-red-500';
                                            } elseif (str_starts_with($entityId, 'cover.')) {
                                                $icon = 'fa-window-maximize';
                                                $iconColor = 'text-indigo-600';
                                            } elseif (str_starts_with($entityId, 'media_player.')) {
                                                $icon = 'fa-tv';
                                                $iconColor = 'text-purple-600';
                                            } elseif (str_starts_with($entityId, 'binary_sensor.')) {
                                                $icon = 'fa-door-open';
                                                $iconColor = 'text-orange-600';
                                            } elseif (str_starts_with($entityId, 'camera.')) {
                                                $icon = 'fa-camera';
                                                $iconColor = 'text-pink-600';
                                            } elseif (str_starts_with($entityId, 'lock.')) {
                                                $icon = 'fa-lock';
                                                $iconColor = 'text-gray-700';
                                            } elseif (str_starts_with($entityId, 'fan.')) {
                                                $icon = 'fa-fan';
                                                $iconColor = 'text-cyan-600';
                                            }
                                        @endphp
                                        <i class="fas {{ $icon }} {{ $iconColor }} text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold truncate">
                                            {{ $switch['attributes']['friendly_name'] ?? $switch['entity_id'] }}
                                        </p>
                                        @if (isset($switch['area_name']) && $switch['area_name'])
                                            <p class="text-xs text-blue-600 truncate">
                                                <i class="fas fa-map-marker-alt"></i> {{ $switch['area_name'] }}
                                            </p>
                                        @endif
                                        <p class="text-xs text-gray-500 truncate">{{ $switch['entity_id'] }}</p>
                                    </div>
                                    <i class="flex-shrink-0 text-gray-400 fas fa-grip-vertical"></i>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-gray-400">
                                <i class="mb-2 text-3xl fas fa-plug"></i>
                                <p>Keine Geräte gefunden</p>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        <!-- Hauptbereich für Dashboard -->
        <div class="flex flex-col flex-1 bg-gray-50">
            <div class="flex items-center justify-between p-4 bg-white border-b border-gray-300">
                <h1 class="text-2xl font-bold text-gray-800">Mein Dashboard</h1>

                <button wire:click="saveDashboard" wire:loading.attr="disabled"
                    class="flex items-center gap-2 px-6 py-2 text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    <i class="fas fa-save" wire:loading.remove wire:target="saveDashboard"></i>
                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="saveDashboard"></i>
                    <span wire:loading.remove wire:target="saveDashboard">Speichern</span>
                    <span wire:loading wire:target="saveDashboard">Speichern...</span>
                </button>
            </div>

            @if (session()->has('success'))
                <div class="p-4 m-4 text-green-700 bg-green-100 border border-green-400 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="p-4 m-4 text-red-700 bg-red-100 border border-red-400 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex-1 p-6 overflow-y-auto" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @if (count($dashboardLayout) === 0)
                        <div class="py-20 text-center text-gray-400 col-span-full">
                            <i class="mb-4 text-4xl fas fa-arrow-left"></i>
                            <p class="text-lg">Ziehe Geräte hierher, um dein Dashboard zu erstellen</p>
                        </div>
                    @else
                        @foreach ($dashboardLayout as $entityId)
                            @livewire('device-card', ['entityId' => $entityId], key($entityId))
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function handleDragStart(event, entityId, friendlyName, state) {
                event.dataTransfer.effectAllowed = 'copy';
                event.dataTransfer.setData('entity-id', entityId);
                event.dataTransfer.setData('friendly-name', friendlyName);
                event.dataTransfer.setData('state', state);
            }

            function handleDragOver(event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
            }

            function handleDrop(event) {
                event.preventDefault();
                const entityId = event.dataTransfer.getData('entity-id');

                if (entityId) {
                    @this.call('addToDashboard', entityId);
                }
            }
        </script>
    @endpush
</div>
