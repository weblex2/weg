<!-- Navigation -->
<nav class="main-nav">
    <div class="container mx-auto">
        <div class="flex items-center justify-between px-4 py-4">
            <!-- Logo/Brand -->
            <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-10 h-10 bg-white rounded-lg">
                    <i class="text-xl text-purple-600 fas fa-home"></i>
                </div>
                <span class="text-xl font-bold text-white">Home Assistant</span>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden space-x-2 md:flex">
                <a href="{{ route('homeassistant.dashboard') }}"
                    class="nav-link flex items-center px-4 py-2 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('homeassistant.dashboard') ? 'active' : '' }}">
                    <i class="mr-2 fas fa-gauge-high"></i>
                    Dashboard
                </a>
                <a href="{{ route('homeassistant.dashboard2') }}"
                    class="nav-link flex items-center px-4 py-3 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('homeassistant.dashboard2') ? 'active' : '' }}">
                    <i class="mr-3 fas fa-gauge-high"></i>
                    Dashboard2
                </a>
                <a href="{{ route('homeassistant.monitor') }}"
                    class="nav-link flex items-center px-4 py-2 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('homeassistant.monitor') ? 'active' : '' }}">
                    <i class="mr-2 fas fa-desktop"></i>
                    Monitor
                </a>
                <a href="{{ route('scheduled-jobs.index') }}"
                    class="nav-link flex items-center px-4 py-2 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('scheduled-jobs.*') ? 'active' : '' }}">
                    <i class="mr-2 fas fa-clock"></i>
                    Scheduler
                </a>
                <a href="{{ route('settings') }}"
                    class="nav-link flex items-center px-4 py-2 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('homeassistant.settings') ? 'active' : '' }}">
                    <i class="mr-2 fa-solid fa-gear"></i>
                    Settings
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <button onclick="toggleMobileMenu()" class="text-white md:hidden focus:outline-none">
                <i class="text-2xl fas fa-bars" id="menu-icon"></i>
            </button>
        </div>

        <!-- Mobile Navigation -->
        <div id="mobile-menu" class="mobile-menu md:hidden">
            <div class="px-4 pb-4 space-y-2">
                <a href="{{ route('homeassistant.dashboard') }}"
                    class="nav-link flex items-center px-4 py-3 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('homeassistant.dashboard') ? 'active' : '' }}">
                    <i class="mr-3 fas fa-gauge-high"></i>
                    Dashboard
                </a>
                <a href="{{ route('homeassistant.dashboard2') }}"
                    class="nav-link flex items-center px-4 py-3 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('homeassistant.dashboard2') ? 'active' : '' }}">
                    <i class="mr-3 fas fa-gauge-high"></i>
                    Dashboard2
                </a>
                <a href="{{ route('homeassistant.monitor') }}"
                    class="nav-link flex items-center px-4 py-3 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('homeassistant.monitor') ? 'active' : '' }}">
                    <i class="mr-3 fas fa-desktop"></i>
                    Monitor
                </a>
                <a href="{{ route('scheduled-jobs.index') }}"
                    class="nav-link flex items-center px-4 py-3 text-white rounded-lg hover:bg-white hover:bg-opacity-10 {{ request()->routeIs('scheduled-jobs.*') ? 'active' : '' }}">
                    <i class="mr-3 fas fa-clock"></i>
                    Scheduler
                </a>
            </div>
        </div>
    </div>
</nav>
