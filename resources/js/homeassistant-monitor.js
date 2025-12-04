/**
 * Home Assistant Monitor JavaScript
 */

// Global State
let devices = new Map();
let updateInterval = null;
let autoUpdateEnabled = true;
let currentFilter = 'all';
let viewMode = 'entities';

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    loadDevices();
    startAutoUpdate();
    initializeEventListeners();
});

// Event Listeners
function initializeEventListeners() {
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('entity-type-dropdown');
        const button = dropdown?.previousElementSibling;

        if (dropdown && button && !dropdown.contains(event.target) && !button.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', stopAutoUpdate);
}

// Dropdown Functions
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    dropdown.classList.toggle('hidden');
}

function filterByType(type) {
    currentFilter = type;
    const dropdown = document.getElementById('entity-type-dropdown');
    const selectedText = document.getElementById('selected-type-text');

    dropdown.classList.add('hidden');

    const typeNames = {
        'all': 'Alle Typen',
        'switch': 'Switches',
        'light': 'Lichter',
        'sensor': 'Sensoren',
        'climate': 'Klima',
        'cover': 'Jalousien',
        'media_player': 'Media Player'
    };

    selectedText.textContent = typeNames[type] || 'Alle Typen';
    filterDevices();
}

// Filter Functions
function filterDevices() {
    const searchInput = document.getElementById('search-input').value.toLowerCase();
    const deviceCards = document.querySelectorAll('.device-card');

    deviceCards.forEach(card => {
        const entityId = card.dataset.entityId.toLowerCase();
        const friendlyName = card.dataset.friendlyName.toLowerCase();

        const matchesSearch = friendlyName.includes(searchInput) || entityId.includes(searchInput);
        const matchesType = currentFilter === 'all' || entityId.startsWith(currentFilter + '.');

        card.style.display = (matchesSearch && matchesType) ? '' : 'none';
    });
}

// Device Loading
async function loadDevices() {
    try {
        document.getElementById('errorMessage').classList.add('hidden');
        updateStatus(true, 'Lade...');

        const endpoint = viewMode === 'devices' ? '/homeassistant/ws/devices' : '/homeassistant/ws/entities';
        const response = await fetch(endpoint);
        const data = await response.json();

        if (data.success) {
            if (viewMode === 'devices') {
                updateDevicesList(data.devices);
            } else {
                updateDevicesList(data.entities);
            }
            updateStatus(true, 'Verbunden');
            document.getElementById('deviceCount').textContent =
                `${data.count} ${viewMode === 'devices' ? 'Geräte' : 'Entities'}`;

            const loadingMsg = document.getElementById('loading-message');
            if (loadingMsg) loadingMsg.remove();
        } else {
            showError('Fehler beim Laden der Geräte');
            updateStatus(false, 'Fehler');
        }
    } catch (error) {
        console.error('Fehler:', error);
        showError('Verbindungsfehler: ' + error.message);
        updateStatus(false, 'Getrennt');
    }
}

function updateDevicesList(entities) {
    const grid = document.getElementById('devices-grid');

    entities.forEach(entity => {
        const entityKey = viewMode === 'devices' ? entity.id : entity.entity_id;
        const displayId = viewMode === 'devices' ? entity.id : entity.entity_id;
        const displayName = viewMode === 'devices' ? entity.name : entity.name;
        const displayState = viewMode === 'devices' ? 'N/A' : entity.state;

        const oldState = devices.get(entityKey)?.state;
        const hasChanged = oldState !== undefined && oldState !== displayState;

        devices.set(entityKey, {
            ...entity,
            state: displayState
        });

        let card = document.getElementById(`device-${entityKey?.replace(/\./g, '-')}`);

        if (!card) {
            card = createDeviceCard({
                ...entity,
                entity_id: displayId,
                name: displayName,
                state: displayState
            });
            grid.appendChild(card);
        } else {
            updateDeviceCard(card, {
                ...entity,
                state: displayState
            }, hasChanged);
        }
    });
}

// Device Card Functions
function createDeviceCard(entity) {
    const card = document.createElement('div');
    const entityId = entity.entity_id || entity.id;
    const cardId = `device-${entityId?.replace(/\./g, '-')}`;
    card.id = cardId;
    card.className = 'device-card';
    card.dataset.entityId = entityId;
    card.dataset.friendlyName = entity.name;

    const { icon, iconColor } = getEntityIcon(entityId);
    const stateClass = getStateClass(entity.state);
    const domain = entityId?.split('.')[0] || 'unknown';

    const isControllable = ['switch', 'light', 'cover', 'fan'].includes(domain);

    const additionalInfo = viewMode === 'devices' ? `
        ${entity.manufacturer ? `
            <div class="device-card-row">
                <span class="device-card-label">Hersteller:</span>
                <span class="device-card-value">${entity.manufacturer}</span>
            </div>
        ` : ''}
        ${entity.model ? `
            <div class="device-card-row">
                <span class="device-card-label">Modell:</span>
                <span class="device-card-value">${entity.model}</span>
            </div>
        ` : ''}
        ${entity.area_id ? `
            <div class="device-card-row">
                <span class="device-card-label">Bereich:</span>
                <span class="device-card-value">${entity.area_id}</span>
            </div>
        ` : ''}
    ` : '';

    const controlButtons = (viewMode === 'entities' && isControllable) ? `
        <div class="device-card-controls">
            ${domain === 'cover' ? `
                <button onclick="controlDevice('${entityId}', '${domain}', 'open_cover')"
                    class="control-button control-button-on">
                    <i class="fas fa-arrow-up mr-1"></i> Auf
                </button>
                <button onclick="controlDevice('${entityId}', '${domain}', 'close_cover')"
                    class="control-button control-button-off">
                    <i class="fas fa-arrow-down mr-1"></i> Zu
                </button>
            ` : `
                <button onclick="controlDevice('${entityId}', '${domain}', 'turn_on')"
                    class="control-button control-button-on">
                    <i class="fas fa-power-off mr-1"></i> Ein
                </button>
                <button onclick="controlDevice('${entityId}', '${domain}', 'turn_off')"
                    class="control-button control-button-off">
                    <i class="fas fa-power-off mr-1"></i> Aus
                </button>
            `}
        </div>
    ` : '';

    card.innerHTML = `
        <div class="device-card-header">
            <div class="device-card-info">
                <div class="device-card-icon ${iconColor}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="device-card-text">
                    <h3 class="device-card-title">${entity.name}</h3>
                    <p class="device-card-subtitle">${entityId}</p>
                </div>
            </div>
            ${entity.state ? `<span class="device-state ${stateClass}">${entity.state}</span>` : ''}
        </div>

        <div class="device-card-body">
            <div class="device-card-row">
                <span class="device-card-label">Typ:</span>
                <span class="device-card-value">${domain}</span>
            </div>
            ${entity.device_class ? `
                <div class="device-card-row">
                    <span class="device-card-label">Klasse:</span>
                    <span class="device-card-value">${entity.device_class}</span>
                </div>
            ` : ''}
            ${additionalInfo}
            <div class="device-card-row">
                <span class="device-card-label">Aktualisiert:</span>
                <span class="device-card-value device-time">${new Date().toLocaleTimeString('de-DE')}</span>
            </div>
        </div>

        ${controlButtons}
    `;

    return card;
}

function updateDeviceCard(card, entity, hasChanged) {
    const stateElement = card.querySelector('.device-state');
    if (stateElement) {
        const stateClass = getStateClass(entity.state);
        stateElement.className = `device-state ${stateClass}`;
        stateElement.textContent = entity.state;
    }

    const timeElement = card.querySelector('.device-time');
    if (timeElement) {
        timeElement.textContent = new Date().toLocaleTimeString('de-DE');
    }

    if (hasChanged) {
        card.classList.add('device-updated');
        setTimeout(() => card.classList.remove('device-updated'), 500);
    }
}

// Device Control
async function controlDevice(entityId, domain, service) {
    try {
        const buttons = document.querySelectorAll('.control-button');
        buttons.forEach(btn => btn.disabled = true);

        const response = await fetch('/homeassistant/ws/service', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({
                domain: domain,
                service: service,
                entity_id: entityId
            })
        });

        const data = await response.json();

        if (data.success) {
            showSuccess(`${entityId} erfolgreich gesteuert`);
            setTimeout(() => loadDevices(), 500);
        } else {
            showError(`Fehler beim Steuern von ${entityId}: ${data.error || 'Unbekannter Fehler'}`);
        }
    } catch (error) {
        console.error('Control error:', error);
        showError(`Verbindungsfehler: ${error.message}`);
    } finally {
        const buttons = document.querySelectorAll('.control-button');
        buttons.forEach(btn => btn.disabled = false);
    }
}

// Utility Functions
function getEntityIcon(entityId) {
    const iconMap = {
        'switch.': { icon: 'fa-toggle-on', iconColor: 'icon-blue' },
        'light.': { icon: 'fa-lightbulb', iconColor: 'icon-yellow' },
        'sensor.': { icon: 'fa-gauge', iconColor: 'icon-green' },
        'climate.': { icon: 'fa-temperature-half', iconColor: 'icon-red' },
        'cover.': { icon: 'fa-window-maximize', iconColor: 'icon-indigo' },
        'media_player.': { icon: 'fa-tv', iconColor: 'icon-purple' },
        'binary_sensor.': { icon: 'fa-door-open', iconColor: 'icon-orange' },
        'camera.': { icon: 'fa-camera', iconColor: 'icon-pink' },
        'lock.': { icon: 'fa-lock', iconColor: 'icon-gray' },
        'fan.': { icon: 'fa-fan', iconColor: 'icon-cyan' }
    };

    for (const [prefix, style] of Object.entries(iconMap)) {
        if (entityId.startsWith(prefix)) {
            return style;
        }
    }

    return { icon: 'fa-question-circle', iconColor: 'icon-gray' };
}

function getStateClass(state) {
    if (!state) return 'status-unavailable';
    const lowerState = state.toLowerCase();
    if (lowerState === 'on' || lowerState === 'home' || lowerState === 'open') return 'status-on';
    if (lowerState === 'off' || lowerState === 'away' || lowerState === 'closed') return 'status-off';
    return 'status-unavailable';
}

// Status Functions
function updateStatus(connected, text) {
    const dot = document.getElementById('statusDot');
    const statusText = document.getElementById('statusText');

    if (connected) {
        dot.className = 'ha-status-dot connected';
    } else {
        dot.className = 'ha-status-dot disconnected';
    }

    statusText.textContent = text;
}

function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');

    setTimeout(() => {
        errorDiv.classList.add('hidden');
    }, 5000);
}

function showSuccess(message) {
    const successDiv = document.getElementById('successMessage');
    successDiv.textContent = message;
    successDiv.classList.remove('hidden');

    setTimeout(() => {
        successDiv.classList.add('hidden');
    }, 3000);
}

// Auto-Update Functions
function startAutoUpdate() {
    if (!updateInterval) {
        updateInterval = setInterval(loadDevices, 1000);
    }
}

function stopAutoUpdate() {
    if (updateInterval) {
        clearInterval(updateInterval);
        updateInterval = null;
    }
}

function toggleAutoUpdate() {
    autoUpdateEnabled = !autoUpdateEnabled;
    const btn = document.getElementById('auto-update-btn');

    if (autoUpdateEnabled) {
        startAutoUpdate();
        btn.innerHTML = '<i class="fas fa-play"></i><span>Auto-Update: An</span>';
        btn.className = 'ha-button ha-button-primary';
    } else {
        stopAutoUpdate();
        btn.innerHTML = '<i class="fas fa-pause"></i><span>Auto-Update: Aus</span>';
        btn.className = 'ha-button ha-button-gray';
    }
}

// View Mode Functions
function toggleViewMode() {
    viewMode = viewMode === 'entities' ? 'devices' : 'entities';
    const btn = document.getElementById('view-mode-btn');

    if (viewMode === 'devices') {
        btn.innerHTML = '<i class="fas fa-microchip"></i><span>Devices</span>';
    } else {
        btn.innerHTML = '<i class="fas fa-layer-group"></i><span>Entities</span>';
    }

    const grid = document.getElementById('devices-grid');
    grid.innerHTML = `
        <div class="ha-loading" id="loading-message">
            <i class="fas fa-spinner ha-loading-icon"></i>
            <p class="ha-loading-text">Lade ${viewMode === 'devices' ? 'Geräte' : 'Entities'}...</p>
        </div>
    `;
    devices.clear();

    loadDevices();
}
