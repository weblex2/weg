{{-- resources/views/livewire/settings.blade.php --}}

<div class="min-h-screen px-4 py-8 bg-gray-50">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-lg shadow-md">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200">
                <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                <p class="mt-1 text-sm text-gray-600">Konfiguriere deine Home Assistant und IMAP Verbindung</p>
            </div>

            {{-- Erfolgs-Nachricht --}}
            @if ($successMessage)
                <div class="flex items-center gap-3 p-4 mx-6 mt-4 border border-green-200 rounded-lg bg-green-50">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="font-medium text-green-800">Settings erfolgreich gespeichert!</span>
                </div>
            @endif

            {{-- Test Messages --}}
            @if (session()->has('test_success'))
                <div class="p-4 mx-6 mt-4 border border-green-200 rounded-lg bg-green-50">
                    <span class="text-green-800">{{ session('test_success') }}</span>
                </div>
            @endif

            @if (session()->has('test_error'))
                <div class="p-4 mx-6 mt-4 border border-red-200 rounded-lg bg-red-50">
                    <span class="text-red-800">{{ session('test_error') }}</span>
                </div>
            @endif

            <form wire:submit.prevent="save" class="p-6 space-y-8">

                {{-- Home Assistant Section --}}
                <div>
                    <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-gray-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                            </path>
                        </svg>
                        Home Assistant
                    </h2>

                    <div class="space-y-4">
                        {{-- HA URL --}}
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">
                                Home Assistant URL <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="ha_url" placeholder="http://homeassistant.local:8123"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('ha_url') border-red-500 @enderror">
                            @error('ha_url')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- HA Token --}}
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">
                                Long-Lived Access Token <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="{{ $showHaToken ? 'text' : 'password' }}" wire:model="ha_token"
                                    placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                                    class="w-full px-4 py-2 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('ha_token') border-red-500 @enderror">
                                <button type="button" wire:click="$toggle('showHaToken')"
                                    class="absolute text-gray-500 -translate-y-1/2 right-3 top-1/2 hover:text-gray-700">
                                    @if ($showHaToken)
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                            </path>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    @endif
                                </button>
                            </div>
                            @error('ha_token')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Token in Home Assistant unter: Profil → Sicherheit → Long-Lived Access Tokens
                            </p>
                        </div>

                        {{-- Test Button --}}
                        <div>
                            <button type="button" wire:click="testConnection"
                                class="px-4 py-2 text-sm font-medium text-white transition-colors bg-gray-600 rounded-lg hover:bg-gray-700">
                                Verbindung testen
                            </button>
                        </div>
                    </div>
                </div>

                {{-- IMAP Section --}}
                <div class="pt-6 border-t border-gray-200">
                    <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-gray-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                            </path>
                        </svg>
                        IMAP Settings
                    </h2>

                    <div class="space-y-4">
                        {{-- IMAP Host --}}
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">
                                IMAP Host
                            </label>
                            <input type="text" wire:model="imap_host" placeholder="imap.gmail.com"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('imap_host') border-red-500 @enderror">
                            @error('imap_host')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- IMAP Port & Encryption --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">
                                    Port
                                </label>
                                <input type="number" wire:model="imap_port" placeholder="993"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('imap_port') border-red-500 @enderror">
                                @error('imap_port')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">
                                    Verschlüsselung
                                </label>
                                <select wire:model="imap_encryption"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="ssl">SSL</option>
                                    <option value="tls">TLS</option>
                                    <option value="none">Keine</option>
                                </select>
                            </div>
                        </div>

                        {{-- IMAP Username --}}
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">
                                E-Mail / Username
                            </label>
                            <input type="email" wire:model="imap_username" placeholder="user@example.com"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('imap_username') border-red-500 @enderror">
                            @error('imap_username')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- IMAP Password --}}
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">
                                Passwort
                            </label>
                            <div class="relative">
                                <input type="{{ $showImapPassword ? 'text' : 'password' }}" wire:model="imap_password"
                                    placeholder="••••••••"
                                    class="w-full px-4 py-2 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('imap_password') border-red-500 @enderror">
                                <button type="button" wire:click="$toggle('showImapPassword')"
                                    class="absolute text-gray-500 -translate-y-1/2 right-3 top-1/2 hover:text-gray-700">
                                    @if ($showImapPassword)
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                            </path>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    @endif
                                </button>
                            </div>
                            @error('imap_password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="pt-6 border-t border-gray-200">
                    <button type="submit" wire:loading.attr="disabled"
                        class="flex items-center justify-center w-full gap-2 px-6 py-3 font-medium text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <svg wire:loading.remove class="w-5 h-5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                        <svg wire:loading class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span wire:loading.remove>Settings speichern</span>
                        <span wire:loading>Speichert...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@script
    <script>
        $wire.on('settings-saved', () => {
            setTimeout(() => {
                $wire.set('successMessage', false);
            }, 3000);
        });
    </script>
@endscript
