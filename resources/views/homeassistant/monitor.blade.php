<x-layout>
    <div class="ha-container">
        <!-- Linke Sidebar mit Status -->
        <div class="ha-sidebar">
            <div class="ha-sidebar-header">
                <h2 class="ha-sidebar-title">Home Assistant Monitor</h2>

                <!-- Status-Anzeige -->
                <div class="ha-status">
                    <div class="ha-status-info">
                        <div id="statusDot" class="ha-status-dot connected"></div>
                        <span id="statusText" class="ha-status-text">Verbunden</span>
                    </div>
                    <span id="deviceCount" class="ha-device-count">0 Geräte</span>
                </div>

                <!-- Suchfeld -->
                <div class="ha-search-wrapper">
                    <input type="text" id="search-input" placeholder="Geräte suchen..." class="ha-search-input"
                        onkeyup="filterDevices()">
                    <i class="fas fa-search ha-search-icon"></i>
                </div>

                <!-- Entity Type Filter -->
                <div class="ha-dropdown">
                    <button onclick="toggleDropdown('entity-type-dropdown')" class="ha-dropdown-button">
                        <i class="mr-2 fas fa-filter"></i>
                        <span id="selected-type-text">Alle Typen</span>
                        <i class="float-right mt-1 fas fa-chevron-down"></i>
                    </button>

                    <div id="entity-type-dropdown" class="hidden ha-dropdown-menu">
                        <div class="ha-dropdown-menu-inner">
                            <button onclick="filterByType('all')" class="ha-dropdown-item">
                                <i class="mr-2 fas fa-list"></i> Alle Typen
                            </button>
                            <div class="ha-dropdown-divider"></div>
                            <button onclick="filterByType('switch')" class="ha-dropdown-item">
                                <i class="mr-2 fas fa-toggle-on"></i> Switches
                            </button>
                            <button onclick="filterByType('light')" class="ha-dropdown-item">
                                <i class="mr-2 fas fa-lightbulb"></i> Lichter
                            </button>
                            <button onclick="filterByType('sensor')" class="ha-dropdown-item">
                                <i class="mr-2 fas fa-gauge"></i> Sensoren
                            </button>
                            <button onclick="filterByType('climate')" class="ha-dropdown-item">
                                <i class="mr-2 fas fa-temperature-half"></i> Klima
                            </button>
                            <button onclick="filterByType('cover')" class="ha-dropdown-item">
                                <i class="mr-2 fas fa-window-maximize"></i> Jalousien
                            </button>
                            <button onclick="filterByType('media_player')" class="ha-dropdown-item">
                                <i class="mr-2 fas fa-tv"></i> Media Player
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ha-sidebar-content" id="devices-list">
                <!-- Geräte werden hier eingefügt -->
            </div>
        </div>

        <!-- Hauptbereich für Live-Anzeige -->
        <div class="ha-main">
            <div class="ha-header">
                <h1 class="ha-header-title">Live Device Monitor</h1>

                <div class="ha-header-actions">
                    <button onclick="toggleViewMode()" id="view-mode-btn" class="ha-button ha-button-outline">
                        <i class="fas fa-layer-group"></i>
                        <span>Entities</span>
                    </button>
                    <button onclick="loadDevices()" class="ha-button ha-button-secondary">
                        <i class="fas fa-sync-alt"></i>
                        <span>Aktualisieren</span>
                    </button>
                    <button onclick="toggleAutoUpdate()" id="auto-update-btn" class="ha-button ha-button-primary">
                        <i class="fas fa-play"></i>
                        <span>Auto-Update: An</span>
                    </button>
                </div>
            </div>

            <div id="errorMessage" class="hidden ha-message ha-message-error"></div>
            <div id="successMessage" class="hidden ha-message ha-message-success"></div>

            <div class="ha-content">
                <div class="ha-devices-grid" id="devices-grid">
                    <div class="ha-loading" id="loading-message">
                        <i class="fas fa-spinner ha-loading-icon"></i>
                        <p class="ha-loading-text">Lade Geräte...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        @vite(['resources/js/homeassistant-monitor.js'])
    @endpush
</x-layout>
