<nav {{ $attributes }} aria-label="{{ __('Main navigation') }}">
    <x-nav-link :href="route('tenant.dashboard')" :active="request()->routeIs('tenant.dashboard')">
        {{ __('Dashboard') }}
    </x-nav-link>
    <x-nav-link :href="route('tenant.services')" :active="request()->routeIs('tenant.services')">
        {{ __('Services') }}
    </x-nav-link>
    <x-nav-link :href="route('tenant.staff')" :active="request()->routeIs('tenant.staff')">
        {{ __('Staff') }}
    </x-nav-link>
    <x-nav-link :href="route('tenant.calendar')" :active="request()->routeIs('tenant.calendar')">
        {{ __('Calendar') }}
    </x-nav-link>
    @can('update', tenant())
        <x-nav-link :href="route('tenant.settings')" :active="request()->routeIs('tenant.settings')">
            {{ __('Settings') }}
        </x-nav-link>
    @endcan
</nav>
