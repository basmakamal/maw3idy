<x-layouts.app :title="$staff->name">
    <div class="mb-4">
        <a href="{{ route('tenant.staff') }}" class="text-sm font-medium text-stone-600 hover:text-stone-900">← {{ __('All staff') }}</a>
    </div>

    <livewire:staff.staff-schedule :staff="$staff" />
</x-layouts.app>
