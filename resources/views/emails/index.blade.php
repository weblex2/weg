<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WEG Dokumentenmanagement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dropzone {
            border: 3px dashed #cbd5e0;
            transition: all 0.3s;
        }

        .dropzone.dragover {
            border-color: #4299e1;
            background-color: #ebf8ff;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 0;
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-gray-50">

    <div class="container px-4 py-8 mx-auto max-w-7xl">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="mb-2 text-3xl font-bold text-gray-800">
                <i class="text-blue-600 fas fa-folder-open"></i>
                WEG Dokumentenmanagement
                <!-- Button zum Öffnen des Modals -->
                <div class="mt-6">
                    <button onclick="openEmailModal()"
                        class="px-6 py-2 text-white transition bg-green-600 rounded-lg hover:bg-green-700">
                        <i class="fas fa-envelope"></i> E-Mail Adressen verwalten
                    </button>
                </div>
            </h1>
            <p class="text-gray-600">Verwalten Sie Ihre Emails und Dokumente</p>
        </div>


        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="px-4 py-3 mb-6 text-green-700 bg-green-100 border border-green-400 rounded">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="px-4 py-3 mb-6 text-red-700 bg-red-100 border border-red-400 rounded">
                {{ session('error') }}
            </div>
        @endif

        <!-- Drag & Drop Upload Zone -->
        <div id="dropzone" class="p-8 mb-6 text-center bg-white rounded-lg cursor-pointer dropzone">
            <i class="mb-4 text-6xl text-gray-400 fas fa-cloud-upload-alt"></i>
            <h3 class="mb-2 text-xl font-semibold text-gray-700">Email hier ablegen</h3>
            <p class="mb-4 text-gray-500">Ziehen Sie .eml oder .msg Dateien hierher oder klicken Sie zum Auswählen</p>
            <input type="file" id="fileInput" accept=".eml,.msg" multiple class="hidden">
            <button onclick="document.getElementById('fileInput').click()"
                class="px-6 py-2 text-white transition bg-blue-600 rounded-lg hover:bg-blue-700">
                Datei auswählen
            </button>
        </div>

        <!-- Upload Progress -->
        <div id="uploadProgress" class="hidden p-4 mb-6 bg-white rounded-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="font-semibold text-gray-700">Uploading...</span>
                <span id="progressPercent" class="text-gray-600">0%</span>
            </div>
            <div class="w-full h-2 bg-gray-200 rounded-full">
                <div id="progressBar" class="h-2 transition-all bg-blue-600 rounded-full" style="width: 0%"></div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
            <form method="GET" action="{{ route('emails.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <!-- Suche -->
                    <div class="md:col-span-3">
                        <label class="block mb-2 text-sm font-medium text-gray-700">
                            <i class="fas fa-search"></i> Suche
                        </label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Betreff, Absender, Inhalt durchsuchen..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Datum von -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700">
                            <i class="fas fa-calendar"></i> Von Datum
                        </label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Datum bis -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700">
                            <i class="fas fa-calendar"></i> Bis Datum
                        </label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Topic Filter -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700">
                            <i class="fas fa-tag"></i> Thema
                        </label>
                        <select name="topic"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Alle Themen</option>
                            <option value="Heizung" {{ request('topic') == 'Heizung' ? 'selected' : '' }}>Heizung
                            </option>
                            <option value="Dach" {{ request('topic') == 'Dach' ? 'selected' : '' }}>Dach</option>
                            <option value="Fassade" {{ request('topic') == 'Fassade' ? 'selected' : '' }}>Fassade
                            </option>
                            <option value="Eigentümerversammlung"
                                {{ request('topic') == 'Eigentümerversammlung' ? 'selected' : '' }}>
                                Eigentümerversammlung</option>
                            <option value="Jahresabrechnung"
                                {{ request('topic') == 'Jahresabrechnung' ? 'selected' : '' }}>Jahresabrechnung
                            </option>
                            <option value="Reparaturen" {{ request('topic') == 'Reparaturen' ? 'selected' : '' }}>
                                Reparaturen</option>
                            <option value="Versicherung" {{ request('topic') == 'Versicherung' ? 'selected' : '' }}>
                                Versicherung</option>
                            <option value="Sonstiges" {{ request('topic') == 'Sonstiges' ? 'selected' : '' }}>Sonstiges
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="px-6 py-2 text-white transition bg-blue-600 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-filter"></i> Filtern
                    </button>
                    <a href="{{ route('emails.index') }}"
                        class="px-6 py-2 text-gray-700 transition bg-gray-200 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-redo"></i> Zurücksetzen
                    </a>

                </div>
            </form>
        </div>

        <!-- Emails List -->
        <div class="space-y-4">
            @forelse($emails as $email)
                <div class="p-6 transition bg-white rounded-lg shadow-sm hover:shadow-md">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start">
                        <!-- Email Content -->
                        <div class="flex-1">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="text-lg font-semibold text-gray-800">
                                    {{ $email->subject }}
                                </h3>
                                <span class="ml-4 text-sm text-gray-500 whitespace-nowrap">
                                    {{ $email->received_at->format('d.m.Y H:i') }}
                                </span>
                            </div>

                            <div class="mb-2 text-sm text-gray-600">
                                <i class="fas fa-user"></i>
                                <strong>Von:</strong> {{ $email->from_name ?? $email->from_email }}
                                @if ($email->from_name)
                                    <span class="text-gray-400">&lt;{{ $email->from_email }}&gt;</span>
                                @endif
                            </div>

                            <p class="mb-4 text-gray-700">{{ $email->preview }}</p>



                            <!-- Attachments -->
                            @if ($email->attachments->count() > 0)
                                <div class="pt-4 border-t">
                                    <div class="mb-3 text-sm font-medium text-gray-700">
                                        <i class="fas fa-paperclip"></i>
                                        {{ $email->attachments->count() }}
                                        Anhang{{ $email->attachments->count() > 1 ? 'e' : '' }}
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($email->attachments as $attachment)
                                            <button
                                                onclick="showAttachment({{ $attachment->id }}, '{{ $attachment->original_filename }}', '{{ route('attachments.view', $attachment) }}', true)"
                                                class="inline-flex items-center gap-2 px-4 py-2 text-sm transition bg-gray-100 rounded-lg hover:bg-gray-200">
                                                <i class="fas {{ $attachment->icon_class }} text-gray-600"></i>
                                                <span
                                                    class="text-gray-700">{{ Str::limit($attachment->original_filename, 30) }}</span>
                                                <span
                                                    class="text-xs text-gray-500">{{ $attachment->formatted_size }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 md:flex-col">
                            <a href="{{ route('emails.show', $email) }}"
                                class="px-4 py-2 text-sm text-center text-white transition bg-blue-600 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            <form method="POST" action="{{ route('emails.destroy', $email) }}"
                                onsubmit="return confirm('Email wirklich löschen?')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="w-full px-4 py-2 text-sm text-white transition bg-red-600 rounded-lg hover:bg-red-700">
                                    <i class="fas fa-trash"></i> Löschen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center bg-white rounded-lg shadow-sm">
                    <i class="mb-4 text-6xl text-gray-300 fas fa-inbox"></i>
                    <h3 class="mb-2 text-xl font-semibold text-gray-700">Keine Emails gefunden</h3>
                    <p class="text-gray-500">Laden Sie Ihre erste Email hoch oder passen Sie Ihre Suchfilter an.</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if ($emails->hasPages())
            <div class="mt-6">
                {{ $emails->links() }}
            </div>
        @endif
    </div>

    <!-- Email Addresses Modal -->
    <div id="emailModal" class="modal">
        <div class="z-20 w-full max-w-lg modal-content">
            <div class="flex items-center justify-between px-6 py-4 text-white bg-gray-800">
                <h3 class="text-lg font-semibold">E-Mail Adressen verwalten</h3>
                <button onclick="closeEmailModal()" class="text-2xl text-white hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 bg-gray-100">
                <form id="emailForm" class="flex gap-2 mb-4">
                    <input type="email" name="email" placeholder="Neue E-Mail Adresse" required
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <button type="submit"
                        class="px-4 py-2 text-white transition bg-blue-600 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus"></i> Hinzufügen
                    </button>
                </form>

                <ul id="emailList" class="space-y-2">
                    <!-- E-Mail-Adressen werden dynamisch geladen -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Attachment Modal -->
    <div id="attachmentModal" class="modal">
        <div class="modal-content">
            <div class="flex items-center justify-between px-6 py-4 text-white bg-gray-800">
                <h3 id="modalTitle" class="text-lg font-semibold"></h3>
                <button onclick="closeModal()" class="text-2xl text-white hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex items-center justify-center p-4 bg-gray-100" style="min-height: 400px;">
                <iframe id="modalIframe" class="w-full h-full" style="min-height: 600px; border: none;"></iframe>
                <div id="modalNoPreview" class="hidden text-center">
                    <i class="mb-4 text-6xl text-gray-400 fas fa-file"></i>
                    <p class="mb-4 text-gray-600">Vorschau nicht verfügbar</p>
                    <a id="modalDownload" href="#"
                        class="inline-block px-6 py-2 text-white transition bg-blue-600 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-download"></i> Herunterladen
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');

        // Drag & Drop Events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
        });

        dropzone.addEventListener('drop', handleDrop, false);
        fileInput.addEventListener('change', handleFiles, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({
                target: {
                    files
                }
            });
        }

        function handleFiles(e) {
            const files = Array.from(e.target.files);
            files.forEach(uploadFile);
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('email_file', file);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            uploadProgress.classList.remove('hidden');

            fetch('{{ route('emails.store') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    }
                    throw new Error('Upload fehlgeschlagen');
                })
                .then(() => {
                    progressBar.style.width = '100%';
                    progressPercent.textContent = '100%';
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                })
                .catch(error => {
                    alert('Fehler beim Upload: ' + error.message);
                    uploadProgress.classList.add('hidden');
                });

            // Simulate progress (in real app, use XMLHttpRequest for real progress)
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                if (progress <= 90) {
                    progressBar.style.width = progress + '%';
                    progressPercent.textContent = progress + '%';
                } else {
                    clearInterval(interval);
                }
            }, 200);
        }

        // Modal Functions
        function showAttachment(id, filename, previewUrl, canPreview) {
            const modal = document.getElementById('attachmentModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalIframe = document.getElementById('modalIframe');
            const modalNoPreview = document.getElementById('modalNoPreview');
            const modalDownload = document.getElementById('modalDownload');

            modalTitle.textContent = filename;
            modalDownload.href = `/attachments/${id}/download`;

            if (canPreview && previewUrl) {
                modalIframe.classList.remove('hidden');
                modalNoPreview.classList.add('hidden');

                // Wichtig: src NACH dem Einblenden setzen
                modalIframe.src = '';
                setTimeout(() => {
                    modalIframe.src = previewUrl;
                }, 100);

                // Debug: Zeige URL in Console
                console.log('Loading preview:', previewUrl);

                // Error handling für iframe
                modalIframe.onerror = function() {
                    console.error('Failed to load:', previewUrl);
                    modalIframe.classList.add('hidden');
                    modalNoPreview.classList.remove('hidden');
                };
            } else {
                modalIframe.classList.add('hidden');
                modalNoPreview.classList.remove('hidden');
            }

            modal.classList.add('active');
        }

        function closeModal() {
            const modal = document.getElementById('attachmentModal');
            const modalIframe = document.getElementById('modalIframe');
            modal.classList.remove('active');
            modalIframe.src = '';
        }

        // Close modal on outside click
        document.getElementById('attachmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });



        const emailModal = document.getElementById('emailModal');
        const emailList = document.getElementById('emailList');
        const emailForm = document.getElementById('emailForm');

        // Öffnen/Schließen
        function openEmailModal() {
            emailModal.classList.add('active');
            //loadEmailAddresses();
        }

        function closeEmailModal() {
            emailModal.classList.remove('active');
        }

        // Laden der Adressen
        function loadEmailAddresses() {
            fetch('{{ route('email_addresses.index') }}')
                .then(res => res.json())
                .then(data => {
                    emailList.innerHTML = '';
                    data.forEach(addr => {
                        const li = document.createElement('li');
                        li.className = 'flex items-center justify-between px-4 py-2 bg-white border rounded-lg';
                        li.innerHTML = `
                            <span>${addr.email}</span>
                            <button onclick="deleteEmail(${addr.id})" class="text-red-600 hover:text-red-800">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                        emailList.appendChild(li);
                    });
                });
        }

        // Neue Adresse hinzufügen
        emailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(emailForm);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            fetch('{{ route('email_addresses.store') }}', {
                    method: 'POST',
                    body: formData
                }).then(res => res.json())
                .then(() => {
                    emailForm.reset();
                    loadEmailAddresses();
                });
        });

        // Adresse löschen
        function deleteEmail(id) {
            if (!confirm('Diese E-Mail wirklich löschen?')) return;

            fetch(`/email_addresses/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).then(() => loadEmailAddresses());
        }

        // Modal schließen auf Klick außerhalb oder ESC
        emailModal.addEventListener('click', function(e) {
            if (e.target === this) closeEmailModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEmailModal();
        });
    </script>
</body>

</html>
