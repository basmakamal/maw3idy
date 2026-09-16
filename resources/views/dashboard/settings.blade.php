<x-layouts.app :title="__('Settings')">
    <div class="max-w-2xl rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        <h2 class="text-base font-semibold">{{ __('General') }}</h2>
        <p class="mt-1 text-sm text-stone-600">{{ __('How your business appears to customers and how times are displayed.') }}</p>

        <div class="mt-6">
            <livewire:settings.general-settings />
        </div>
    </div>
</x-layouts.app>
