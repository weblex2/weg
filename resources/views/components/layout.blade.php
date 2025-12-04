<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mails')</title>

    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/css/ha.css', 'resources/js/homeassistant-monitor.js', 'resources/js/app.js'])

    @livewireStyles
</head>

<body>
    {{ $slot }} {{-- hier wird der Inhalt eingefügt --}}
    @livewireScripts
</body>

</html>
