@props(['title', 'description', 'phase' => null])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center']) }}>
    <h2 class="text-lg font-semibold">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-stone-600">{{ $description }}</p>
    @if ($phase)
        <p class="mt-4 text-xs font-medium uppercase tracking-wide text-teal-700">
            {{ __('Coming in phase :phase', ['phase' => $phase]) }}
        </p>
    @endif
</div>
