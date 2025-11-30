    <div class="flex h-screen bg-gray-50">
        <!-- Linke Spalte: E-Mail-Auswahl -->
        <div class="w-1/3 h-full overflow-y-auto border-r border-gray-200" style="background-color: #86efac;">
            <div class="flex flex-col p-4">
                <h2 class="mb-4 text-lg font-semibold text-left text-gray-800">123E-Mails</h2>
                <ul class="w-full space-y-1 text-left">
                    @foreach ($emails as $email)
                        <li>
                            <button class="w-full p-2 text-left bg-white rounded"
                                wire:click="selectEmail({{ $email->id }})"
                                class="w-full text-left p-2 rounded hover:bg-gray-100 @if ($selectedEmail && $selectedEmail->id === $email->id) bg-gray-200 font-semibold @endif">

                                <div class="w-full text-sm text-left text-gray-700">
                                    {{ \Carbon\Carbon::parse($email->received_at)->format('d.m.Y H:i') }}
                                </div>
                                <div class="w-full text-sm text-left text-gray-700">
                                    {{ $email->subject }}
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>



        <!-- Rechte Spalte: E-Mail-Inhalt -->
        <div class="flex-1 p-6 overflow-y-auto text-left">
            @if ($selectedEmail)
                <h2 class="mb-4 text-xl font-bold">{{ $selectedEmail->subject }}</h2>
                <p class="mb-4 text-sm text-gray-500">
                    Von: {{ $selectedEmail->from }} | Empfang:
                    {{ \Carbon\Carbon::parse($selectedEmail->received_at)->format('d.m.Y H:i') }}
                </p>

                <div class="prose text-left break-words max-w-none">
                    @php
                        $body = $selectedEmail->body;
                        $body = preg_replace('/<o:p>.*?<\/o:p>/', '', $body);
                        $body = str_replace(['<p class="MsoNormal">', '</p>'], ['<p>', '</p>'], $body);

                        if (strip_tags($body) === $body) {
                            $body = nl2br(e($body));
                        }
                    @endphp

                    {!! $body !!}
                </div>
            @else
                <div class="mt-4 text-left text-gray-400">
                    Wähle eine E-Mail aus der Liste aus.
                </div>
            @endif
        </div>
    </div>
