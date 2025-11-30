<x-layout>
    <div class="flex h-screen">
        <!-- Linke Sidebar mit verfügbaren Switches -->
        <div class="flex flex-col bg-gray-100 border-r border-gray-300 w-80">
            <div class="p-4 bg-white border-b border-gray-300">
                <h2 class="mb-3 text-xl font-bold text-gray-800">Verfügbare Geräte</h2>

                <!-- Suchfeld -->
                <div class="relative mb-3">
                    <input type="text" id="search-input" placeholder="Geräte suchen..."
                        class="w-full px-3 py-2 pl-10 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onkeyup="filterSwitches()">
                    <i class="absolute text-gray-400 fas fa-search left-3 top-3"></i>

                    <!-- Entity Type Dropdown Button -->
                    <div class="absolute right-2 top-2">
                        <button onclick="toggleDropdown('entity-type-dropdown')"
                            class="px-2 py-1 text-gray-600 hover:text-gray-800 focus:outline-none"
                            id="entity-dropdown-button">
                            <i class="fas fa-filter"></i>
                        </button>

                        <!-- Entity Type Dropdown Menu -->
                        <div id="entity-type-dropdown"
                            class="absolute right-0 z-10 hidden w-48 mt-2 bg-white border border-gray-300 rounded-lg shadow-lg">
                            <div class="py-1">
                                <button onclick="filterByType('all')"
                                    class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-list"></i> Alle Typen
                                </button>
                                <button onclick="filterByType('switch')"
                                    class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-toggle-on"></i> Switches
                                </button>
                                <button onclick="filterByType('light')"
                                    class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-lightbulb"></i> Lichter
                                </button>
                                <button onclick="filterByType('sensor')"
                                    class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-gauge"></i> Sensoren
                                </button>
                                <button onclick="filterByType('climate')"
                                    class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-temperature-half"></i> Klima
                                </button>
                                <button onclick="filterByType('cover')"
                                    class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-window-maximize"></i> Jalousien
                                </button>
                                <button onclick="filterByType('media_player')"
                                    class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-tv"></i> Media Player
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Area Filter Dropdown -->
                <div class="relative">
                    <button onclick="toggleDropdown('area-dropdown')"
                        class="w-full px-3 py-2 text-left bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="area-dropdown-button">
                        <i class="mr-2 fas fa-map-marker-alt"></i>
                        <span id="selected-area-text">Alle Bereiche</span>
                        <i class="float-right mt-1 fas fa-chevron-down"></i>
                    </button>

                    <!-- Area Dropdown Menu -->
                    <div id="area-dropdown"
                        class="absolute left-0 right-0 z-10 hidden mt-2 overflow-y-auto bg-white border border-gray-300 rounded-lg shadow-lg max-h-64">
                        <div class="py-1">
                            <button onclick="filterByArea('all')"
                                class="w-full px-4 py-2 text-left transition-colors area-item hover:bg-gray-100">
                                <i class="mr-2 fas fa-list"></i> Alle Bereiche
                            </button>
                            @foreach ($areas as $area)
                                <button onclick="filterByArea('{{ $area }}')"
                                    class="w-full px-4 py-2 text-left transition-colors area-item hover:bg-gray-100">
                                    <i class="mr-2 fas fa-map-marker-alt"></i> {{ $area }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-4 space-y-3 overflow-y-auto" id="sidebar-switches">
                @foreach ($switches as $switch)
                    <div class="p-3 transition-shadow bg-white border rounded shadow-sm cursor-move hover:shadow-md switch-item"
                        draggable="true" data-entity-id="{{ $switch['entity_id'] }}"
                        data-friendly-name="{{ $switch['attributes']['friendly_name'] ?? $switch['entity_id'] }}"
                        data-state="{{ $switch['state'] }}" data-area-name="{{ $switch['area_name'] ?? '' }}"
                        ondragstart="handleDragStart(event)">
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
                @endforeach
            </div>
        </div>

        <!-- Hauptbereich für Dashboard -->
        <div class="flex flex-col flex-1 bg-gray-50">
            <div class="flex items-center justify-between p-4 bg-white border-b border-gray-300">
                <h1 class="text-2xl font-bold text-gray-800">Mein Dashboard</h1>

                <button onclick="saveDashboard()" id="save-button"
                    class="flex items-center gap-2 px-6 py-2 text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-save"></i>
                    <span>Speichern</span>
                </button>
            </div>

            <div class="flex-1 p-6 overflow-y-auto" id="dashboard-area" ondrop="handleDrop(event)"
                ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" id="dashboard-grid">
                    <div class="py-20 text-center text-gray-400 col-span-full" id="empty-message">
                        <i class="mb-4 text-4xl fas fa-arrow-left"></i>
                        <p class="text-lg">Ziehe Geräte hierher, um dein Dashboard zu erstellen</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .dragging {
            opacity: 0.5;
        }

        .drag-over {
            background-color: #e0f2fe;
        }

        .dashboard-switch {
            transition: all 0.3s ease;
        }

        .dashboard-switch:hover .remove-btn {
            opacity: 1;
        }

        .remove-btn {
            opacity: 0;
            transition: opacity 0.2s;
        }
    </style>

    <script>
        let draggedElement = null;
        let currentEntityType = 'all';
        let currentArea = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            loadSavedDashboard();
        });

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            dropdown.classList.toggle('hidden');

            const allDropdowns = ['entity-type-dropdown', 'area-dropdown'];
            allDropdowns.forEach(id => {
                if (id !== dropdownId) {
                    document.getElementById(id).classList.add('hidden');
                }
            });
        }

        document.addEventListener('click', function(event) {
            const entityDropdown = document.getElementById('entity-type-dropdown');
            const entityButton = document.getElementById('entity-dropdown-button');
            const areaDropdown = document.getElementById('area-dropdown');
            const areaButton = document.getElementById('area-dropdown-button');

            if (entityDropdown && entityButton && !entityDropdown.contains(event.target) && !entityButton.contains(
                    event.target)) {
                entityDropdown.classList.add('hidden');
            }

            if (areaDropdown && areaButton && !areaDropdown.contains(event.target) && !areaButton.contains(event
                    .target)) {
                areaDropdown.classList.add('hidden');
            }
        });

        function filterByType(type) {
            currentEntityType = type;
            const dropdown = document.getElementById('entity-type-dropdown');
            dropdown.classList.add('hidden');
            filterSwitches();
        }

        function filterByArea(area) {
            currentArea = area;
            const dropdown = document.getElementById('area-dropdown');
            const selectedText = document.getElementById('selected-area-text');

            dropdown.classList.add('hidden');
            selectedText.textContent = area === 'all' ? 'Alle Bereiche' : area;

            filterSwitches();
        }

        function filterSwitches() {
            const searchInput = document.getElementById('search-input').value.toLowerCase();
            const switches = document.querySelectorAll('#sidebar-switches .switch-item');

            switches.forEach(switchItem => {
                const friendlyName = switchItem.dataset.friendlyName.toLowerCase();
                const entityId = switchItem.dataset.entityId.toLowerCase();
                const areaName = (switchItem.dataset.areaName || '').toLowerCase();

                const matchesSearch = friendlyName.includes(searchInput) || entityId.includes(searchInput);

                let matchesType = true;
                if (currentEntityType !== 'all') {
                    matchesType = entityId.startsWith(currentEntityType + '.');
                }

                let matchesArea = true;
                if (currentArea !== 'all') {
                    matchesArea = areaName === currentArea.toLowerCase();
                }

                if (matchesSearch && matchesType && matchesArea) {
                    switchItem.style.display = '';
                } else {
                    switchItem.style.display = 'none';
                }
            });
        }

        function handleDragStart(event) {
            draggedElement = event.target;
            event.target.classList.add('dragging');

            const entityId = event.target.dataset.entityId;
            const friendlyName = event.target.dataset.friendlyName;
            const state = event.target.dataset.state;

            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/html', event.target.innerHTML);
            event.dataTransfer.setData('entity-id', entityId);
            event.dataTransfer.setData('friendly-name', friendlyName);
            event.dataTransfer.setData('state', state);
        }

        function handleDragOver(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            document.getElementById('dashboard-area').classList.add('drag-over');
        }

        function handleDragLeave(event) {
            if (event.target.id === 'dashboard-area') {
                event.target.classList.remove('drag-over');
            }
        }

        function handleDrop(event) {
            event.preventDefault();
            document.getElementById('dashboard-area').classList.remove('drag-over');

            const entityId = event.dataTransfer.getData('entity-id');
            const friendlyName = event.dataTransfer.getData('friendly-name');
            const state = event.dataTransfer.getData('state');

            if (document.getElementById('dashboard-' + entityId.replace(/\./g, '-'))) {
                return;
            }

            addSwitchToDashboard(entityId, friendlyName, state);

            const emptyMsg = document.getElementById('empty-message');
            if (emptyMsg) {
                emptyMsg.remove();
            }

            if (draggedElement) {
                draggedElement.classList.remove('dragging');
            }
        }

        function addSwitchToDashboard(entityId, friendlyName, state) {
            const grid = document.getElementById('dashboard-grid');
            const switchId = 'dashboard-' + entityId.replace(/\./g, '-');

            const switchCard = document.createElement('div');
            switchCard.id = switchId;
            switchCard.className = 'dashboard-switch bg-white p-6 rounded-lg shadow-md border border-gray-200 relative';
            switchCard.dataset.entityId = entityId;
            switchCard.innerHTML = `
                <button
                    onclick="removeSwitchFromDashboard('${switchId}')"
                    class="absolute text-red-500 remove-btn top-2 right-2 hover:text-red-700"
                >
                    <i class="fas fa-times"></i>
                </button>

                <div class="text-center">
                    <h3 class="mb-2 text-lg font-bold">${friendlyName}</h3>
                    <p class="mb-4 text-sm text-gray-500">${entityId}</p>

                    <button
                        onclick="toggleSwitch('${entityId}', '${switchId}')"
                        class="w-full py-3 transition-colors cursor-pointer toggle-btn hover:opacity-80"
                    >
                        ${state === 'off'
                            ? '<i class="text-5xl text-gray-400 fas fa-toggle-off"></i>'
                            : '<i class="text-5xl text-blue-500 fas fa-toggle-on"></i>'}
                    </button>

                    <p class="mt-2 text-sm">Status: <span class="font-semibold status-text">${state}</span></p>
                </div>
            `;

            grid.appendChild(switchCard);
        }

        function removeSwitchFromDashboard(switchId) {
            const element = document.getElementById(switchId);
            if (element) {
                element.remove();
            }

            const grid = document.getElementById('dashboard-grid');
            if (grid.children.length === 0) {
                grid.innerHTML = `
                    <div class="py-20 text-center text-gray-400 col-span-full" id="empty-message">
                        <i class="mb-4 text-4xl fas fa-arrow-left"></i>
                        <p class="text-lg">Ziehe Geräte hierher, um dein Dashboard zu erstellen</p>
                    </div>
                `;
            }
        }

        function toggleSwitch(entityId, containerId) {
            const container = document.getElementById(containerId);
            const button = container.querySelector('.toggle-btn');
            const icon = button.querySelector('i');
            const statusText = container.querySelector('.status-text');

            button.style.opacity = '0.5';

            fetch('/homeassistant/toggle/' + encodeURIComponent(entityId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fetch('/homeassistant/state/' + encodeURIComponent(entityId))
                            .then(response => response.json())
                            .then(stateData => {
                                if (stateData.success) {
                                    const newState = stateData.state.state;

                                    if (newState === 'off') {
                                        icon.className = 'text-gray-400 fas fa-toggle-off text-5xl';
                                    } else {
                                        icon.className = 'text-blue-500 fas fa-toggle-on text-5xl';
                                    }

                                    statusText.textContent = newState;
                                    button.style.opacity = '1';
                                }
                            });
                    } else {
                        alert('Fehler beim Schalten: ' + (data.error || 'Unbekannter Fehler'));
                        button.style.opacity = '1';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Verbindungsfehler: ' + error.message);
                    button.style.opacity = '1';
                });
        }

        function saveDashboard() {
            const saveButton = document.getElementById('save-button');
            const originalContent = saveButton.innerHTML;

            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Speichern...</span>';

            const dashboardItems = document.querySelectorAll('#dashboard-grid .dashboard-switch');
            const layout = [];

            dashboardItems.forEach(item => {
                layout.push(item.dataset.entityId);
            });

            fetch('/homeassistant/dashboard/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        layout: layout,
                        name: 'Mein Dashboard'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        saveButton.innerHTML = '<i class="fas fa-check"></i> <span>Gespeichert!</span>';
                        saveButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                        saveButton.classList.add('bg-green-600');

                        setTimeout(() => {
                            saveButton.innerHTML = originalContent;
                            saveButton.classList.remove('bg-green-600');
                            saveButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
                            saveButton.disabled = false;
                        }, 2000);
                    } else {
                        alert('Fehler beim Speichern: ' + (data.error || 'Unbekannter Fehler'));
                        saveButton.innerHTML = originalContent;
                        saveButton.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Verbindungsfehler: ' + error.message);
                    saveButton.innerHTML = originalContent;
                    saveButton.disabled = false;
                });
        }

        function loadSavedDashboard() {
            @if (isset($savedDashboard) && $savedDashboard)
                const savedLayout = @json($savedDashboard->layout);

                if (savedLayout && savedLayout.length > 0) {
                    const emptyMsg = document.getElementById('empty-message');
                    if (emptyMsg) {
                        emptyMsg.remove();
                    }

                    savedLayout.forEach(entityId => {
                        fetch('/homeassistant/state/' + encodeURIComponent(entityId))
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const state = data.state.state;
                                    const friendlyName = data.state.attributes.friendly_name || entityId;
                                    addSwitchToDashboard(entityId, friendlyName, state);
                                }
                            })
                            .catch(error => {
                                console.error('Error loading entity:', entityId, error);
                            });
                    });
                }
            @endif
        }
    </script>
</x-layout>
