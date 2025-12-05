<x-layout>
    <!-- Irgendwo in deinem Blade-Template -->
    <div class="deploy-section">
        <button id="deployBtn" class="btn btn-primary">
            🚀 Deploy starten
        </button>
        <div id="deployStatus" style="margin-top: 10px;"></div>
    </div>

    <script>
        document.getElementById('deployBtn').addEventListener('click', async function() {
            const btn = this;
            const status = document.getElementById('deployStatus');

            // Button deaktivieren
            btn.disabled = true;
            btn.textContent = '⏳ Deploy läuft...';
            status.innerHTML = '<div class="alert alert-info">Deploy wird ausgeführt...</div>';

            try {
                const response = await fetch('/deploy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    status.innerHTML = `
                <div class="alert alert-success">
                    ✅ ${data.message}
                    <pre>${data.output || ''}</pre>
                </div>
            `;
                } else {
                    status.innerHTML = `
                <div class="alert alert-danger">
                    ❌ Fehler: ${data.message || data.error}
                </div>
            `;
                }
            } catch (error) {
                status.innerHTML = `
            <div class="alert alert-danger">
                ❌ Fehler: ${error.message}
            </div>
        `;
            } finally {
                // Button wieder aktivieren
                btn.disabled = false;
                btn.textContent = '🚀 Deploy starten';
            }
        });
    </script>

    <style>
        .deploy-section {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }

        #deployBtn {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
        }

        #deployBtn:hover:not(:disabled) {
            background: #0056b3;
        }

        #deployBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            overflow-x: auto;
        }
    </style>
</x-layout>
