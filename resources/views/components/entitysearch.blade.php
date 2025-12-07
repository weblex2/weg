<!-- Device Search Component -->
<div class="device-search-component">
    <!-- Suchfeld -->
    <div class="relative mb-3">
        <input type="text" id="{{ $searchId ?? 'device-search-input' }}" placeholder="Geräte suchen..."
            class="w-full px-3 py-2 pl-10 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            onkeyup="{{ $onSearch ?? 'filterDevices()' }}">
        <i class="absolute text-gray-400 fas fa-search left-3 top-3"></i>

        <!-- Entity Type Dropdown Button -->
        <div class="absolute right-2 top-2">
            <button onclick="toggleDropdown('{{ $typeDropdownId ?? 'entity-type-dropdown' }}')" type="button"
                class="px-2 py-1 text-gray-600 hover:text-gray-800 focus:outline-none"
                id="{{ $typeButtonId ?? 'entity-dropdown-button' }}">
                <i class="fas fa-filter"></i>
            </button>

            <!-- Entity Type Dropdown Menu -->
            <div id="{{ $typeDropdownId ?? 'entity-type-dropdown' }}"
                class="absolute right-0 z-10 hidden w-48 mt-2 bg-white border border-gray-300 rounded-lg shadow-lg">
                <div class="py-1">
                    <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('all')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-list"></i> Alle Typen
                    </button>
                    @if ($showAreas ?? true)
                        <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('areas')" type="button"
                            class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                            <i class="mr-2 fas fa-map-marker-alt"></i> Bereiche
                        </button>
                    @endif
                    <div class="my-1 border-t border-gray-200"></div>
                    <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('switch')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-toggle-on"></i> Switches
                    </button>
                    <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('light')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-lightbulb"></i> Lichter
                    </button>
                    <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('sensor')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-gauge"></i> Sensoren
                    </button>
                    <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('climate')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-temperature-half"></i> Klima
                    </button>
                    <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('cover')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-window-maximize"></i> Jalousien
                    </button>
                    <button onclick="{{ $onTypeFilter ?? 'filterByType' }}('media_player')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors dropdown-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-tv"></i> Media Player
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Area Filter Dropdown -->
    @if ($showAreaFilter ?? true)
        <div class="relative">
            <button onclick="toggleDropdown('{{ $areaDropdownId ?? 'area-dropdown' }}')" type="button"
                class="w-full px-3 py-2 text-left bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                id="{{ $areaButtonId ?? 'area-dropdown-button' }}">
                <i class="mr-2 fas fa-map-marker-alt"></i>
                <span id="{{ $areaTextId ?? 'selected-area-text' }}">Alle Bereiche</span>
                <i class="float-right mt-1 fas fa-chevron-down"></i>
            </button>

            <!-- Area Dropdown Menu -->
            <div id="{{ $areaDropdownId ?? 'area-dropdown' }}"
                class="absolute left-0 right-0 z-10 hidden mt-2 overflow-y-auto bg-white border border-gray-300 rounded-lg shadow-lg max-h-64">
                <div class="py-1">
                    <button onclick="{{ $onAreaFilter ?? 'filterByArea' }}('all')" type="button"
                        class="w-full px-4 py-2 text-left transition-colors area-item hover:bg-gray-100">
                        <i class="mr-2 fas fa-list"></i> Alle Bereiche
                    </button>
                    @if (isset($areas) && is_array($areas))
                        @foreach ($areas as $area)
                            <button onclick="{{ $onAreaFilter ?? 'filterByArea' }}('{{ $area }}')"
                                type="button"
                                class="w-full px-4 py-2 text-left transition-colors area-item hover:bg-gray-100">
                                <i class="mr-2 fas fa-map-marker-alt"></i> {{ $area }}
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    function toggleDropdown(dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;

        dropdown.classList.toggle('hidden');

        // Close other dropdowns
        const allDropdowns = ['entity-type-dropdown', 'area-dropdown', '{{ $typeDropdownId ?? '' }}',
            '{{ $areaDropdownId ?? '' }}'
        ];
        allDropdowns.forEach(id => {
            if (id && id !== dropdownId) {
                const otherDropdown = document.getElementById(id);
                if (otherDropdown) {
                    otherDropdown.classList.add('hidden');
                }
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const dropdownIds = [{
                dropdown: '{{ $typeDropdownId ?? 'entity-type-dropdown' }}',
                button: '{{ $typeButtonId ?? 'entity-dropdown-button' }}'
            },
            {
                dropdown: '{{ $areaDropdownId ?? 'area-dropdown' }}',
                button: '{{ $areaButtonId ?? 'area-dropdown-button' }}'
            }
        ];

        dropdownIds.forEach(({
            dropdown,
            button
        }) => {
            const dropdownEl = document.getElementById(dropdown);
            const buttonEl = document.getElementById(button);

            if (dropdownEl && buttonEl &&
                !dropdownEl.contains(event.target) &&
                !buttonEl.contains(event.target)) {
                dropdownEl.classList.add('hidden');
            }
        });
    });
</script>
