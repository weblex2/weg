<x-layout>
    <div class="flex h-screen">
        <!-- Linke Sidebar mit verfügbaren Switches -->
        <div class="flex flex-col bg-gray-100 border-r border-gray-300 w-80">
            <div class="p-4 bg-white border-b border-gray-300">
                <h2 class="mb-3 text-xl font-bold text-gray-800">Verfügbare Geräte</h2>
                <div class="relative">
                    <input type="text" id="search-input" placeholder="Geräte suchen..."
                        class="w-full px-3 py-2 pl-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onkeyup="filterSwitches()">
                    <i class="absolute text-gray-400 fas fa-search left-3 top-3"></i>
                </div>
            </div>

            <div class="flex-1 p-4 space-y-3 overflow-y-auto" id="sidebar-switches">
                @foreach ($switches as $switch)
                    <div class="p-3 transition-shadow bg-white border rounded shadow-sm cursor-move hover:shadow-md switch-item"
                        draggable="true" data-entity-id="{{ $switch['entity_id'] }}"
                        data-friendly-name="{{ $switch['attributes']['friendly_name'] ?? $switch['entity_id'] }}"
                        data-state="{{ $switch['state'] }}" ondragstart="handleDragStart(event)">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-semibold">
                                    {{ $switch['attributes']['friendly_name'] ?? $switch['entity_id'] }}</p>
                                <p class="text-xs text-gray-500">{{ $switch['entity_id'] }}</p>
                            </div>
                            <i class="text-gray-400 fas fa-grip-vertical"></i>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Hauptbereich für Dashboard -->
        <div class="flex flex-col flex-1 bg-gray-50">
            <div class="p-4 bg-white border-b border-gray-300">
                <h1 class="text-2xl font-bold text-gray-800">Mein Dashboard</h1>
            </div>

            <div class="flex-1 p-6 overflow-y-auto" id="dashboard-area" ondrop="handleDrop(event)"
                ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" id="dashboard-grid">
                    <!-- Dashboard Items werden hier eingefügt -->
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

        function filterSwitches() {
            const searchInput = document.getElementById('search-input').value.toLowerCase();
            const switches = document.querySelectorAll('#sidebar-switches .switch-item');

            switches.forEach(switchItem => {
                const friendlyName = switchItem.dataset.friendlyName.toLowerCase();
                const entityId = switchItem.dataset.entityId.toLowerCase();

                if (friendlyName.includes(searchInput) || entityId.includes(searchInput)) {
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

            // Prüfe ob das Gerät bereits im Dashboard ist
            if (document.getElementById('dashboard-' + entityId.replace(/\./g, '-'))) {
                return; // Bereits vorhanden
            }

            addSwitchToDashboard(entityId, friendlyName, state);

            // Entferne "Empty Message"
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

            // Zeige "Empty Message" wieder an, wenn keine Switches mehr da sind
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

            // Visuelles Feedback
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
                        // Hole den aktuellen Status
                        fetch('/homeassistant/state/' + encodeURIComponent(entityId))
                            .then(response => response.json())
                            .then(stateData => {
                                if (stateData.success) {
                                    const newState = stateData.state.state;

                                    // Update Icon
                                    if (newState === 'off') {
                                        icon.className = 'text-gray-400 fas fa-toggle-off text-5xl';
                                    } else {
                                        icon.className = 'text-blue-500 fas fa-toggle-on text-5xl';
                                    }

                                    // Update Status Text
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
    </script>
</x-layout>
