<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Mails')</title>

    <!-- Tailwind CSS -->
    @vite('resources/css/app.css')

    @livewireStyles
</head>

<body>
    {{ $slot }} {{-- hier wird der Inhalt eingefügt --}}
    @livewireScripts
</body>

</html>
