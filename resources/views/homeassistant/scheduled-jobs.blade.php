<x-layout>

    <div class="container">
        <div class="header">
            <h1 class="page-title">Geplante Home Assistant Aktionen</h1>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs-border">
                <nav class="tabs-nav">
                    <button onclick="switchTab('scheduled')" id="tab-scheduled" class="tab-button tab-active">
                        Geplante Aktionen
                    </button>
                    <button onclick="switchTab('queue')" id="tab-queue" class="tab-button">
                        Queue Status
                    </button>
                    <button onclick="switchTab('logs')" id="tab-logs" class="tab-button">
                        Logs
                    </button>
                </nav>
            </div>
        </div>

        <!-- Tab Content: Geplante Aktionen -->
        <div id="content-scheduled" class="tab-content">
            <div class="grid-layout">
                <!-- Formular zum Erstellen/Bearbeiten -->
                <div class="form-column">
                    <div class="card">
                        <h2 class="card-title">
                            {{ $scheduledJob->id > 0 ? 'Aktion bearbeiten' : 'Neue Aktion' }}
                        </h2>

                        <form method="POST"
                            action="{{ $scheduledJob->id > 0 ? route('scheduled-jobs.update', $scheduledJob) : route('scheduled-jobs.store') }}">
                            @csrf
                            @if ($scheduledJob->id > 0)
                                @method('PUT')
                            @endif

                            <div class="form-group">
                                <label class="form-label" for="name">Name</label>
                                <input type="text" name="name" id="name"
                                    value="{{ old('name', $scheduledJob->name) }}" class="form-input"
                                    placeholder="z.B. Licht morgens einschalten" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="entity_id">Entity ID</label>

                                <!-- Domain Filter Dropdown -->
                                <div class="relative mb-2">
                                    <button type="button" onclick="toggleEntityDropdown()"
                                        class="flex items-center justify-between text-left form-input"
                                        id="domain-filter-button">
                                        <span>
                                            <i class="mr-2 fas fa-filter"></i>
                                            <span id="selected-domain-text">Alle Domains anzeigen</span>
                                        </span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>

                                    <!-- Domain Dropdown Menu -->
                                    <div id="domain-filter-dropdown"
                                        class="absolute left-0 right-0 z-10 hidden mt-1 overflow-y-auto bg-white border border-gray-300 rounded-lg shadow-lg max-h-64">
                                        <div class="py-1">
                                            <button type="button" onclick="filterEntitiesByDomain('')"
                                                class="w-full px-4 py-2 text-sm text-left transition-colors hover:bg-gray-100">
                                                <i class="mr-2 fas fa-list"></i> Alle Domains anzeigen
                                            </button>
                                            <div class="my-1 border-t border-gray-200"></div>
                                            @foreach ($entities as $domain => $domainEntities)
                                                <button type="button"
                                                    onclick="filterEntitiesByDomain('{{ $domain }}')"
                                                    class="w-full px-4 py-2 text-sm text-left transition-colors hover:bg-gray-100">
                                                    @php
                                                        $icon = 'fa-question-circle';
                                                        if ($domain === 'switch') {
                                                            $icon = 'fa-toggle-on';
                                                        } elseif ($domain === 'light') {
                                                            $icon = 'fa-lightbulb';
                                                        } elseif ($domain === 'sensor') {
                                                            $icon = 'fa-gauge';
                                                        } elseif ($domain === 'climate') {
                                                            $icon = 'fa-temperature-half';
                                                        } elseif ($domain === 'cover') {
                                                            $icon = 'fa-window-maximize';
                                                        } elseif ($domain === 'media_player') {
                                                            $icon = 'fa-tv';
                                                        }
                                                    @endphp
                                                    <i class="fas {{ $icon }} mr-2"></i>
                                                    {{ strtoupper($domain) }} ({{ count($domainEntities) }})
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <select name="entity_id" id="entity_id" class="form-input" required>
                                    <option value="">-- Bitte wählen --</option>
                                    @foreach ($entities as $domain => $domainEntities)
                                        <optgroup label="{{ strtoupper($domain) }} ({{ count($domainEntities) }})"
                                            data-domain="{{ $domain }}">
                                            @foreach ($domainEntities as $entity)
                                                <option value="{{ $entity['entity_id'] }}"
                                                    data-domain="{{ $domain }}"
                                                    {{ old('entity_id', $scheduledJob->entity_id) == $entity['entity_id'] ? 'selected' : '' }}>
                                                    {{ $entity['attributes']['friendly_name'] ?? $entity['entity_id'] }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <p class="form-hint">
                                    Insgesamt {{ array_sum(array_map('count', $entities)) }} Entities verfügbar
                                </p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="action">Aktion</label>
                                <select name="action" id="action" class="form-input" required>
                                    <option value="">-- Bitte wählen --</option>
                                    <option value="turn_on"
                                        {{ old('action', $scheduledJob->action) == 'turn_on' ? 'selected' : '' }}>
                                        Einschalten</option>
                                    <option value="turn_off"
                                        {{ old('action', $scheduledJob->action) == 'turn_off' ? 'selected' : '' }}>
                                        Ausschalten</option>
                                    <option value="toggle"
                                        {{ old('action', $scheduledJob->action) == 'toggle' ? 'selected' : '' }}>
                                        Umschalten</option>
                                    <option value="brightness"
                                        {{ old('action', $scheduledJob->action) == 'brightness' ? 'selected' : '' }}>
                                        Helligkeit setzen</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="scheduled_time">Uhrzeit</label>
                                <input type="time" name="scheduled_time" id="scheduled_time"
                                    value="{{ old('scheduled_time', $scheduledJob->scheduled_time ? \Carbon\Carbon::parse($scheduledJob->scheduled_time)->format('H:i') : '') }}"
                                    class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Wochentage (optional)</label>
                                <div class="weekdays-grid">
                                    @php
                                        $days = [
                                            1 => 'Montag',
                                            2 => 'Dienstag',
                                            3 => 'Mittwoch',
                                            4 => 'Donnerstag',
                                            5 => 'Freitag',
                                            6 => 'Samstag',
                                            7 => 'Sonntag',
                                        ];
                                        $selectedDays = old('weekdays', $scheduledJob->weekdays ?? []);
                                    @endphp
                                    @foreach ($days as $num => $name)
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="weekdays[]" value="{{ $num }}"
                                                {{ in_array($num, $selectedDays) ? 'checked' : '' }}>
                                            <span>{{ $name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="form-hint">Leer lassen für täglich</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="parameters_json">Parameter (JSON, optional)</label>
                                <textarea name="parameters_json" id="parameters_json" rows="3" class="form-textarea"
                                    placeholder='{"brightness": 255}'>{{ old('parameters_json', $scheduledJob->parameters ? json_encode($scheduledJob->parameters) : '') }}</textarea>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_repeating" value="1"
                                        {{ old('is_repeating', $scheduledJob->is_repeating) ? 'checked' : '' }}>
                                    <span class="checkbox-text">Wiederholend</span>
                                </label>
                            </div>

                            <div class="button-group">
                                <button type="submit" class="btn btn-primary">
                                    {{ $scheduledJob->id > 0 ? 'Aktualisieren' : 'Erstellen' }}
                                </button>

                                @if ($scheduledJob->id > 0)
                                    <a href="{{ route('scheduled-jobs.index') }}" class="btn btn-secondary">
                                        Abbrechen
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste der Jobs -->
                <div class="table-column">
                    <div class="card card-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name / Entity</th>
                                    <th>Zeit</th>
                                    <th>Nächste Ausführung</th>
                                    <th>Status</th>
                                    <th class="text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($scheduledJobs as $job)
                                    <tr>
                                        <td>
                                            <div class="job-name">{{ $job->name }}</div>
                                            <div class="job-entity">
                                                @php
                                                    $entity = null;
                                                    foreach ($entities as $domainEntities) {
                                                        foreach ($domainEntities as $e) {
                                                            if ($e['entity_id'] === $job->entity_id) {
                                                                $entity = $e;
                                                                break 2;
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                {{ $entity['attributes']['friendly_name'] ?? $job->entity_id }}
                                            </div>
                                            <div class="job-details">{{ $job->entity_id }} · {{ $job->action }}
                                            </div>
                                            @if ($job->weekdays)
                                                <div class="weekday-tags">
                                                    @foreach ($job->weekdays as $day)
                                                        <span
                                                            class="weekday-tag">{{ ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][$day - 1] }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="nowrap">
                                            <div class="job-time">
                                                {{ \Carbon\Carbon::parse($job->scheduled_time)->format('H:i') }} Uhr
                                            </div>
                                            @if ($job->is_repeating)
                                                <span class="badge badge-blue">Wiederholend</span>
                                            @endif
                                        </td>
                                        <td class="nowrap job-next-run">
                                            @if ($job->next_run_at)
                                                {{ \Carbon\Carbon::parse($job->next_run_at)->format('d.m.Y H:i') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="nowrap">
                                            <form method="POST" action="{{ route('scheduled-jobs.toggle', $job) }}"
                                                class="inline-form">
                                                @csrf
                                                <button type="submit" class="status-button">
                                                    @if ($job->is_active)
                                                        <span class="badge badge-green">Aktiv</span>
                                                    @else
                                                        <span class="badge badge-gray">Inaktiv</span>
                                                    @endif
                                                </button>
                                            </form>
                                        </td>
                                        <td class="nowrap action-cell">
                                            <div class="action-links">
                                                <a href="{{ route('scheduled-jobs.index') }}?copy={{ $job->id }}"
                                                    class="icon-button icon-copy" title="Kopieren">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                </a>
                                                <a href="{{ route('scheduled-jobs.index') }}?edit={{ $job->id }}"
                                                    class="icon-button icon-edit" title="Bearbeiten">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                                <form method="POST"
                                                    action="{{ route('scheduled-jobs.destroy', $job) }}"
                                                    class="inline-form"
                                                    onsubmit="return confirm('Wirklich löschen?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="icon-button icon-delete"
                                                        title="Löschen">
                                                        <svg fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="empty-message">
                                            Keine geplanten Aktionen vorhanden.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if ($scheduledJobs->hasPages())
                            <div class="pagination-container">
                                {{ $scheduledJobs->appends(['tab' => 'scheduled'])->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Queue Status -->
        <div id="content-queue" class="tab-content tab-hidden">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Aktuelle Queue Jobs</h2>
                    <button onclick="refreshQueue()" class="btn btn-primary btn-sm">
                        Aktualisieren
                    </button>
                </div>

                <div id="queue-content">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Job ID</th>
                                <th>Queue</th>
                                <th>Payload</th>
                                <th>Verfügbar um</th>
                                <th>Versuche</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($queueJobs as $queueJob)
                                <tr>
                                    <td class="job-id">#{{ $queueJob->id }}</td>
                                    <td class="queue-name">{{ $queueJob->queue }}</td>
                                    <td>
                                        @php
                                            $payload = json_decode($queueJob->payload, true);
                                            $commandData = unserialize($payload['data']['command'] ?? '');
                                        @endphp
                                        <div class="payload-name">{{ $payload['displayName'] ?? 'Unknown Job' }}</div>
                                        @if (isset($commandData->scheduledJob))
                                            <div class="payload-detail">Job:
                                                {{ $commandData->scheduledJob->name ?? 'N/A' }}</div>
                                        @endif
                                    </td>
                                    <td class="nowrap queue-time">
                                        {{ \Carbon\Carbon::createFromTimestamp($queueJob->available_at)->timezone('Europe/Berlin')->format('d.m.Y H:i:s') }}
                                    </td>
                                    <td class="nowrap queue-attempts">{{ $queueJob->attempts }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-message">Keine Jobs in der Queue.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab Content: Logs -->
        <div id="content-logs" class="tab-content tab-hidden">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Logs</h2>
                    <button onclick="refreshLogs()" class="btn btn-primary btn-sm">
                        Aktualisieren
                    </button>
                </div>

                <div id="logs-content">
                    @if (count($logs) > 0)
                        <div class="logs-container">
                            @foreach ($logs as $i => $log)
                                @php
                                    $json = json_decode($log, true);
                                    $type = 'info';
                                    $logClass = 'log-info';

                                    if (is_array($json)) {
                                        $message = $json['message'] ?? '';

                                        if (isset($json['level'])) {
                                            $level = strtolower($json['level']);
                                            if (in_array($level, ['error', 'critical', 'alert', 'emergency'])) {
                                                $type = 'error';
                                                $logClass = 'log-error';
                                            } elseif ($level === 'warning') {
                                                $type = 'warning';
                                                $logClass = 'log-warning';
                                            } elseif (
                                                $level === 'success' ||
                                                str_contains($message, 'successfully') ||
                                                str_contains($message, 'success')
                                            ) {
                                                $type = 'success';
                                                $logClass = 'log-success';
                                            }
                                        } elseif (str_contains($message, 'error') || str_contains($message, 'failed')) {
                                            $type = 'error';
                                            $logClass = 'log-error';
                                        } elseif (
                                            str_contains($message, 'Command Started') ||
                                            str_contains($message, 'Completed')
                                        ) {
                                            $type = 'success';
                                            $logClass = 'log-success';
                                        }

                                        if (isset($json['created_at'])) {
                                            try {
                                                $json['created_at'] = \Carbon\Carbon::parse($json['created_at'])
                                                    ->timezone('Europe/Berlin')
                                                    ->format('d.m.Y H:i:s');
                                            } catch (\Exception $e) {
                                            }
                                        }
                                    }
                                @endphp

                                <div class="log-entry {{ $logClass }}">
                                    <div class="log-content">
                                        <div class="log-header">
                                            <div class="log-meta">
                                                <svg class="log-icon" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    @if ($type === 'error')
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    @elseif ($type === 'warning')
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    @elseif ($type === 'success')
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    @endif
                                                </svg>
                                                <span class="log-number">#{{ $i + 1 }}</span>
                                                @if (is_array($json) && isset($json['created_at']))
                                                    <span class="log-time">{{ $json['created_at'] }}</span>
                                                @endif
                                            </div>
                                            @if (is_array($json) && isset($json['level']))
                                                <span
                                                    class="log-level log-level-{{ $type }}">{{ $json['level'] }}</span>
                                            @endif
                                        </div>

                                        @if (is_array($json))
                                            @if (isset($json['message']))
                                                <div class="log-message">
                                                    <p>{{ $json['message'] }}</p>
                                                </div>
                                            @endif

                                            @php
                                                $excludeKeys = ['message', 'created_at', 'level', 'channel'];
                                                $contextData = array_diff_key($json, array_flip($excludeKeys));
                                            @endphp

                                            @if (count($contextData) > 0)
                                                <details class="log-details">
                                                    <summary class="log-details-summary">
                                                        Details anzeigen ({{ count($contextData) }} Einträge)
                                                    </summary>
                                                    <div class="log-details-content">
                                                        <table class="log-details-table">
                                                            <tbody>
                                                                @foreach ($contextData as $key => $value)
                                                                    <tr>
                                                                        <td class="log-detail-key">{{ $key }}
                                                                        </td>
                                                                        <td class="log-detail-value">
                                                                            @if (is_array($value))
                                                                                <pre>{{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                                                            @elseif (is_bool($value))
                                                                                <span
                                                                                    class="log-bool log-bool-{{ $value ? 'true' : 'false' }}">
                                                                                    {{ $value ? 'true' : 'false' }}
                                                                                </span>
                                                                            @elseif (is_null($value))
                                                                                <span class="log-null">null</span>
                                                                            @else
                                                                                {{ $value }}
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </details>
                                            @endif
                                        @else
                                            <pre class="log-raw">{{ $log }}</pre>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (method_exists($logs, 'links'))
                            <div class="pagination-container">
                                {{ $logs->appends(['tab' => 'logs'])->setPageName('logs_page')->links() }}
                            </div>
                        @endif
                    @else
                        <div class="empty-state">
                            <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="empty-text">Keine Logs vorhanden.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('tab-hidden');
            });

            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('tab-active');
            });

            document.getElementById('content-' + tab).classList.remove('tab-hidden');
            document.getElementById('tab-' + tab).classList.add('tab-active');
        }

        // Check URL parameter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');

            if (tab && ['scheduled', 'queue', 'logs'].includes(tab)) {
                switchTab(tab);
            }
        });

        function toggleEntityDropdown() {
            const dropdown = document.getElementById('domain-filter-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('domain-filter-dropdown');
            const button = document.getElementById('domain-filter-button');

            if (dropdown && button && !dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        function filterEntitiesByDomain(domain) {
            const dropdown = document.getElementById('domain-filter-dropdown');
            const selectedText = document.getElementById('selected-domain-text');
            const entitySelect = document.getElementById('entity_id');
            const optgroups = entitySelect.querySelectorAll('optgroup');
            const options = entitySelect.querySelectorAll('option[data-domain]');

            dropdown.classList.add('hidden');

            if (domain === '') {
                selectedText.textContent = 'Alle Domains anzeigen';
                optgroups.forEach(group => group.style.display = '');
                options.forEach(opt => opt.style.display = '');
            } else {
                selectedText.textContent = domain.toUpperCase();
                optgroups.forEach(group => {
                    if (group.dataset.domain === domain) {
                        group.style.display = '';
                    } else {
                        group.style.display = 'none';
                    }
                });

                options.forEach(opt => {
                    if (opt.dataset.domain === domain) {
                        opt.style.display = '';
                    } else {
                        opt.style.display = 'none';
                    }
                });
            }
        }

        async function refreshQueue() {
            const button = event.target;
            button.disabled = true;
            button.textContent = 'Lädt...';

            try {
                const response = await fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.querySelector('#queue-content');

                if (newContent) {
                    document.getElementById('queue-content').innerHTML = newContent.innerHTML;
                }
            } catch (error) {
                console.error('Fehler beim Aktualisieren:', error);
            } finally {
                button.disabled = false;
                button.textContent = 'Aktualisieren';
            }
        }

        async function refreshLogs() {
            const button = event.target;
            button.disabled = true;
            button.textContent = 'Lädt...';

            try {
                const response = await fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.querySelector('#logs-content');

                if (newContent) {
                    document.getElementById('logs-content').innerHTML = newContent.innerHTML;
                }
            } catch (error) {
                console.error('Fehler beim Aktualisieren:', error);
            } finally {
                button.disabled = false;
                button.textContent = 'Aktualisieren';
            }
        }
    </script>
</x-layout>
