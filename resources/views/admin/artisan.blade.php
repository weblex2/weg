<x-layout>

    @section('title', 'Artisan Commands')

    @section('content')
        <div class="container px-4 py-8 mx-auto">
            <h1 class="mb-8 text-3xl font-bold text-center">Artisan Commands ausführen</h1>

            <div class="grid max-w-5xl grid-cols-1 gap-6 mx-auto md:grid-cols-2 lg:grid-cols-3">
                @php
                    $commands = [
                        ['name' => 'Cache leeren', 'command' => 'cache:clear', 'icon' => '🗑️'],
                        ['name' => 'Config Cache leeren', 'command' => 'config:clear', 'icon' => '⚙️'],
                        ['name' => 'Route Cache leeren', 'command' => 'route:clear', 'icon' => '🛣️'],
                        ['name' => 'View Cache leeren', 'command' => 'view:clear', 'icon' => '👁️'],
                        ['name' => 'Alle Caches leeren', 'command' => 'clear-compiled', 'icon' => '🔥'],
                        [
                            'name' => 'Migrationen ausführen',
                            'command' => 'migrate',
                            'icon' => '🔄',
                            'options' => ['--force' => true],
                        ],
                        ['name' => 'Queue neu starten', 'command' => 'queue:restart', 'icon' => '🔁'],
                        ['name' => 'Queue stoppen', 'command' => 'queue:stop', 'icon' => '🔁'],
                        ['name' => 'Storage Link erstellen', 'command' => 'storage:link', 'icon' => '🔗'],
                    ];
                @endphp

                @foreach ($commands as $cmd)
                    <div class="p-6 transition bg-white rounded-lg shadow-lg hover:shadow-xl">
                        <div class="mb-4 text-4xl text-center">{{ $cmd['icon'] }}</div>
                        <h3 class="mb-4 text-lg font-semibold text-center">{{ $cmd['name'] }}</h3>
                        <code class="block mb-6 text-xs text-center text-gray-600">
                            php artisan {{ $cmd['command'] }}
                        </code>

                        <button type="button"
                            onclick="runCommand('{{ $cmd['command'] }}', {{ isset($cmd['options']) ? json_encode($cmd['options']) : 'null' }})"
                            class="w-full px-4 py-3 font-bold text-white transition bg-blue-600 rounded hover:bg-blue-700">
                            Ausführen
                        </button>
                    </div>
                @endforeach
            </div>

            <!-- Ausgabe-Bereich -->
            <div class="max-w-5xl mx-auto mt-12">
                <h2 class="mb-4 text-2xl font-bold">Ausgabe</h2>
                <pre id="output" class="hidden p-6 overflow-x-auto text-green-400 bg-gray-900 rounded-lg">
            <code id="output-content"></code>
        </pre>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loading" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-50">
            <div class="p-8 bg-white rounded-lg">
                <svg class="w-10 h-10 text-blue-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                    </circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <p class="mt-4 text-lg">Command wird ausgeführt...</p>
            </div>
        </div>

        <script>
            function runCommand(command, options = null) {
                const output = document.getElementById('output');
                const outputContent = document.getElementById('output-content');
                const loading = document.getElementById('loading');

                output.classList.add('hidden');
                outputContent.textContent = '';
                loading.classList.remove('hidden');

                // Korrekte URL-Generierung mit Platzhalter-Ersetzung
                const url = "{{ route('admin.artisan.run', ':command') }}".replace(':command', command);

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: options ? JSON.stringify({
                            options: options
                        }) : null
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loading.classList.add('hidden');

                        if (data.error) {
                            outputContent.textContent = 'Fehler: ' + data.error;
                            output.classList.remove('hidden');
                            output.classList.add('text-red-400');
                            output.classList.remove('text-green-400');
                            return;
                        }

                        let text = `Command: php artisan ${data.command}\n`;
                        text += `Exit Code: ${data.exit_code}\n\n`;
                        text += data.output.trim() || '(keine Ausgabe)';

                        outputContent.textContent = text;
                        output.classList.remove('hidden', 'text-red-400');
                        output.classList.add('text-green-400');
                    })
                    .catch(err => {
                        loading.classList.add('hidden');
                        outputContent.textContent = 'Fehler bei der Anfrage: ' + err.message;
                        output.classList.remove('hidden', 'text-green-400');
                        output.classList.add('text-red-400');
                    });
            }
        </script>
    </x-layout>
