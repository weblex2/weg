<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Home Assistant')</title>

    <!-- CSS -->
    @vite(['resources/css/app.css', 'resources/css/ha.css', 'resources/css/scheduled-jobs.css', 'resources/js/app.js'])

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @stack('styles')
    @livewireStyles

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>

    <style>
        /* Navigation Styles */
        .main-nav {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background: white;
            transition: width 0.3s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 80%;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .mobile-menu {
            transform: translateY(-100%);
            transition: transform 0.3s ease;
        }

        .mobile-menu.open {
            transform: translateY(0);
        }

        body {
            min-height: 100vh;
            background: #f3f4f6;
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">

    <x-hanavigation />
    <!-- Main Content -->
    <main class="flex-1">
        {{ $slot }}
    </main>

    <!-- Footer (optional) -->
    <footer class="py-4 mt-auto text-center text-gray-600 bg-white border-t border-gray-200">
        <p class="text-sm">
            <i class="text-red-500 fas fa-heart"></i>
            Home Assistant Dashboard {{ date('Y') }}
        </p>
    </footer>

    @livewireScripts
    @stack('scripts')

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const icon = document.getElementById('menu-icon');

            menu.classList.toggle('open');

            if (menu.classList.contains('open')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('mobile-menu');
            const button = event.target.closest('button[onclick="toggleMobileMenu()"]');

            if (!button && !menu.contains(event.target) && menu.classList.contains('open')) {
                toggleMobileMenu();
            }
        });

        // Close mobile menu on route change (for SPA-like behavior)
        document.querySelectorAll('#mobile-menu a').forEach(link => {
            link.addEventListener('click', function() {
                const menu = document.getElementById('mobile-menu');
                if (menu.classList.contains('open')) {
                    toggleMobileMenu();
                }
            });
        });
    </script>
</body>

</html>
