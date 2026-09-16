<x-layouts.app :title="__('Dashboard')">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Business') }}</p>
            <p class="mt-2 truncate text-lg font-semibold">{{ tenant()->name }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Booking page') }}</p>
            <p class="mt-2 truncate text-lg font-semibold" dir="ltr">{{ tenant()->domain() }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Timezone') }}</p>
            <p class="mt-2 truncate text-lg font-semibold">{{ tenant()->timezone }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Language') }}</p>
            <p class="mt-2 truncate text-lg font-semibold">{{ \App\Support\Localization::name(tenant()->locale) }}</p>
        </div>
    </div>

    <section class="mt-8">
        <h2 class="text-base font-semibold">{{ __('Get set up') }}</h2>
        <ol class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <li class="rounded-2xl border border-stone-200 bg-white p-5">
                <p class="text-sm font-semibold">1. {{ __('Add your services') }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ __('What customers can book, how long it takes and what it costs.') }}</p>
                <a href="{{ route('tenant.services') }}" class="mt-3 inline-block text-sm font-medium text-teal-700 hover:text-teal-900">{{ __('Services') }} →</a>
            </li>
            <li class="rounded-2xl border border-stone-200 bg-white p-5">
                <p class="text-sm font-semibold">2. {{ __('Add your staff and hours') }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ __('Who does the work and when they are available.') }}</p>
                <a href="{{ route('tenant.staff') }}" class="mt-3 inline-block text-sm font-medium text-teal-700 hover:text-teal-900">{{ __('Staff') }} →</a>
            </li>
            <li class="rounded-2xl border border-stone-200 bg-white p-5">
                <p class="text-sm font-semibold">3. {{ __('Share your booking page') }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ __('Customers pick a service, a time and confirm. You get notified.') }}</p>
                <p class="mt-3 text-sm text-stone-400">{{ __('Available after phase 2') }}</p>
            </li>
        </ol>
    </section>
</x-layouts.app>
