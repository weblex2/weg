<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mails')</title>

    <!-- Nur CSS im Head -->
    @vite(['resources/css/app.css', 'resources/css/ha.css', 'resources/js/app.js'])
    @stack('scripts') {{-- Füge Stack hinzu --}}

    @livewireStyles
</head>

<body>
    {{ $slot }}

    @livewireScripts
</body>

</html>
