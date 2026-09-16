<x-layouts.guest :title="__('Appointment booking for small businesses')">
    <div class="space-y-6 text-center">
        <h1 class="text-3xl font-semibold tracking-tight">
            {{ __('Bookings your customers can make in thirty seconds.') }}
        </h1>

        <p class="text-stone-600">
            {{ __('Maw3idy gives salons, clinics and tutors a booking page on their own address, with staff schedules, buffers and reminders handled for them.') }}
        </p>

        <a href="{{ route('central.register') }}"
           class="inline-flex items-center justify-center rounded-lg bg-teal-700 px-5 py-3 text-sm font-semibold text-white shadow-xs transition hover:bg-teal-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2">
            {{ __('Create your booking page') }}
        </a>

        <p class="text-xs text-stone-500">{{ __('Free while in development. No card required.') }}</p>
    </div>
</x-layouts.guest>
