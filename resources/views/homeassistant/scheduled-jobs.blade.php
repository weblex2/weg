{{-- resources/views/scheduled-jobs/create.blade.php --}}
<x-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Neue geplante Aktion erstellen
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('scheduled-jobs.store') }}" method="POST">
                        @csrf

                        {{-- Entity ID --}}
                        <div class="mb-4">
                            <label for="entity_id" class="block mb-2 text-sm font-medium text-gray-700">
                                Entity ID *
                            </label>
                            <input type="text" name="entity_id" id="entity_id" value="{{ old('entity_id') }}"
                                placeholder="z.B. light.wohnzimmer"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('entity_id') border-red-500 @enderror">
                            @error('entity_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Action --}}
                        <div class="mb-4">
                            <label for="action" class="block mb-2 text-sm font-medium text-gray-700">
                                Aktion *
                            </label>
                            <input type="text" name="action" id="action" value="{{ old('action') }}"
                                placeholder="z.B. turn_on, turn_off"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('action') border-red-500 @enderror">
                            @error('action')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Scheduled Time --}}
                        <div class="mb-4">
                            <label for="scheduled_time" class="block mb-2 text-sm font-medium text-gray-700">
                                Uhrzeit *
                            </label>
                            <input type="time" name="scheduled_time" id="scheduled_time"
                                value="{{ old('scheduled_time') }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('scheduled_time') border-red-500 @enderror">
                            @error('scheduled_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Weekdays --}}
                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-gray-700">
                                Wochentage (leer lassen für täglich)
                            </label>
                            <div class="grid grid-cols-7 gap-2">
                                @php
                                    $days = [
                                        1 => 'Mo',
                                        2 => 'Di',
                                        3 => 'Mi',
                                        4 => 'Do',
                                        5 => 'Fr',
                                        6 => 'Sa',
                                        7 => 'So',
                                    ];
                                @endphp
                                @foreach ($days as $value => $label)
                                    <label
                                        class="flex items-center justify-center p-2 border rounded-md cursor-pointer hover:bg-gray-50 @if (is_array(old('weekdays')) && in_array($value, old('weekdays'))) bg-blue-100 border-blue-500 @endif">
                                        <input type="checkbox" name="weekdays[]" value="{{ $value }}"
                                            class="hidden peer"
                                            {{ is_array(old('weekdays')) && in_array($value, old('weekdays')) ? 'checked' : '' }}>
                                        <span
                                            class="text-sm font-medium peer-checked:text-blue-600">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('weekdays')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Is Repeating --}}
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_repeating" value="1"
                                    {{ old('is_repeating', true) ? 'checked' : '' }}
                                    class="text-blue-600 border-gray-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Wiederkehrend</span>
                            </label>
                        </div>

                        {{-- Parameters --}}
                        <div class="mb-4">
                            <label for="parameters" class="block mb-2 text-sm font-medium text-gray-700">
                                Parameter (JSON)
                            </label>
                            <textarea name="parameters_json" id="parameters" rows="4" placeholder='{"brightness": 255, "color_name": "red"}'
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('parameters') border-red-500 @enderror">{{ old('parameters_json') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Optionale Parameter im JSON-Format</p>
                            @error('parameters')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end mt-6 space-x-3">
                            <a href="{{ route('scheduled-jobs.index') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                Abbrechen
                            </a>
                            <button type="submit"
                                class="px-4 py-2 font-bold text-white bg-blue-500 rounded hover:bg-blue-700">
                                Erstellen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Geplante Aktion bearbeiten
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('scheduled-jobs.update', $scheduledJob) }}" method="POST">
                        @csrf
                        @method('PUT')

                        {{-- Entity ID --}}
                        <div class="mb-4">
                            <label for="entity_id" class="block mb-2 text-sm font-medium text-gray-700">
                                Entity ID *
                            </label>
                            <input type="text" name="entity_id" id="entity_id"
                                value="{{ old('entity_id', $scheduledJob->entity_id) }}"
                                placeholder="z.B. light.wohnzimmer"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('entity_id') border-red-500 @enderror">
                            @error('entity_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Action --}}
                        <div class="mb-4">
                            <label for="action" class="block mb-2 text-sm font-medium text-gray-700">
                                Aktion *
                            </label>
                            <input type="text" name="action" id="action"
                                value="{{ old('action', $scheduledJob->action) }}" placeholder="z.B. turn_on, turn_off"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('action') border-red-500 @enderror">
                            @error('action')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Scheduled Time --}}
                        <div class="mb-4">
                            <label for="scheduled_time" class="block mb-2 text-sm font-medium text-gray-700">
                                Uhrzeit *
                            </label>
                            <input type="time" name="scheduled_time" id="scheduled_time"
                                value="{{ old('scheduled_time', \Carbon\Carbon::parse($scheduledJob->scheduled_time)->format('H:i')) }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('scheduled_time') border-red-500 @enderror">
                            @error('scheduled_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Weekdays --}}
                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-gray-700">
                                Wochentage (leer lassen für täglich)
                            </label>
                            <div class="grid grid-cols-7 gap-2">
                                @php
                                    $days = [
                                        1 => 'Mo',
                                        2 => 'Di',
                                        3 => 'Mi',
                                        4 => 'Do',
                                        5 => 'Fr',
                                        6 => 'Sa',
                                        7 => 'So',
                                    ];
                                    $selectedDays = old('weekdays', $scheduledJob->weekdays ?? []);
                                @endphp
                                @foreach ($days as $value => $label)
                                    <label
                                        class="flex items-center justify-center p-2 border rounded-md cursor-pointer hover:bg-gray-50 @if (is_array($selectedDays) && in_array($value, $selectedDays)) bg-blue-100 border-blue-500 @endif">
                                        <input type="checkbox" name="weekdays[]" value="{{ $value }}"
                                            class="hidden peer"
                                            {{ is_array($selectedDays) && in_array($value, $selectedDays) ? 'checked' : '' }}>
                                        <span
                                            class="text-sm font-medium peer-checked:text-blue-600">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('weekdays')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Is Repeating --}}
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_repeating" value="1"
                                    {{ old('is_repeating', $scheduledJob->is_repeating) ? 'checked' : '' }}
                                    class="text-blue-600 border-gray-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Wiederkehrend</span>
                            </label>
                        </div>

                        {{-- Parameters --}}
                        <div class="mb-4">
                            <label for="parameters" class="block mb-2 text-sm font-medium text-gray-700">
                                Parameter (JSON)
                            </label>
                            <textarea name="parameters_json" id="parameters" rows="4"
                                placeholder='{"brightness": 255, "color_name": "red"}'
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('parameters') border-red-500 @enderror">{{ old('parameters_json', $scheduledJob->parameters ? json_encode($scheduledJob->parameters, JSON_PRETTY_PRINT) : '') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Optionale Parameter im JSON-Format</p>
                            @error('parameters')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Buttons --}}
                        <div class="flex items-center justify-end mt-6 space-x-3">
                            <a href="{{ route('scheduled-jobs.index') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                Abbrechen
                            </a>
                            <button type="submit"
                                class="px-4 py-2 font-bold text-white bg-blue-500 rounded hover:bg-blue-700">
                                Aktualisieren
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layout>
