@php $start = $booking->starts_at->setTimezone($timezone); @endphp
<x-layouts.public :title="__('Booking confirmed')">
    <div class="text-center">
        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-teal-50 text-teal-700" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
        </div>
        <h2 class="mt-4 text-xl font-semibold">{{ __('You\'re booked, :name.', ['name' => $booking->customer_name]) }}</h2>
        <p class="mt-1 text-sm text-stone-600">{{ __('Your booking reference is') }}</p>
        <p class="mt-1 text-2xl font-semibold tracking-wider text-teal-800" dir="ltr">{{ $booking->reference }}</p>
    </div>

    <dl class="mt-8 grid gap-3 rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-stone-500">{{ __('Service') }}</dt><dd class="font-medium">{{ $booking->service->name }}</dd></div>
        <div><dt class="text-stone-500">{{ __('With') }}</dt><dd class="font-medium">{{ $booking->staff->name }}</dd></div>
        <div><dt class="text-stone-500">{{ __('When') }}</dt><dd class="font-medium">{{ $start->translatedFormat('l j F Y') }} · {{ $start->format('H:i') }}–{{ $booking->ends_at->setTimezone($timezone)->format('H:i') }}</dd></div>
        <div><dt class="text-stone-500">{{ __('Timezone') }}</dt><dd class="font-medium">{{ $timezone }}</dd></div>
        <div><dt class="text-stone-500">{{ __('Price') }}</dt><dd class="font-medium">{{ number_format((float) $booking->price, 2) }} {{ config('booking.currency') }}</dd></div>
        <div><dt class="text-stone-500">{{ __('Contact') }}</dt><dd class="font-medium" dir="ltr">{{ $booking->customer_phone }}</dd></div>
    </dl>

    <p class="mt-6 text-center text-sm text-stone-600">
        {{ __('Keep the reference handy; you\'ll need it to change or cancel.') }}
    </p>

    <div class="mt-6 text-center">
        <a href="{{ route('tenant.book') }}" class="text-sm font-medium text-teal-700 hover:text-teal-900">{{ __('Book another appointment') }}</a>
    </div>
</x-layouts.public>
