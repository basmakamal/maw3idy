@php
    $booking = $this->booking;
    $tz = $this->timezone();
    $start = $booking?->starts_at->setTimezone($tz);
@endphp
<div class="space-y-6">
    @if (! $booking)
        <p class="text-sm text-stone-600">{{ __('This booking could not be found.') }}</p>
    @else
        @if ($notice)
            <div class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">{{ $notice }}</div>
        @endif
        @if ($problem)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $problem }}</div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Booking reference') }}</p>
                <p class="text-xl font-semibold tracking-wider text-teal-800" dir="ltr">{{ $booking->reference }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $booking->isConfirmed() ? 'bg-teal-50 text-teal-800' : 'bg-stone-100 text-stone-500' }}">
                {{ $booking->status->label() }}
            </span>
        </div>

        <dl class="grid gap-3 rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm sm:grid-cols-2">
            <div><dt class="text-stone-500">{{ __('Service') }}</dt><dd class="font-medium">{{ $booking->service->name }}</dd></div>
            <div><dt class="text-stone-500">{{ __('With') }}</dt><dd class="font-medium">{{ $booking->staff->name }}</dd></div>
            <div><dt class="text-stone-500">{{ __('When') }}</dt>
                <dd class="font-medium">{{ $start->translatedFormat('l j F Y') }} · {{ $start->format('H:i') }}–{{ $booking->ends_at->setTimezone($tz)->format('H:i') }}</dd></div>
            <div><dt class="text-stone-500">{{ __('Timezone') }}</dt><dd class="font-medium">{{ $tz }}</dd></div>
            @if ($booking->cancellation_reason)
                <div class="sm:col-span-2"><dt class="text-stone-500">{{ __('Cancellation reason') }}</dt><dd class="font-medium">{{ $booking->cancellation_reason }}</dd></div>
            @endif
        </dl>

        @if (! $booking->isConfirmed())
            <div class="rounded-xl border border-stone-200 p-4 text-center">
                <p class="text-sm text-stone-600">{{ __('This booking is cancelled.') }}</p>
                <a href="{{ route('tenant.book') }}" class="mt-2 inline-block text-sm font-medium text-teal-700 hover:text-teal-900">{{ __('Book again') }}</a>
            </div>
        @elseif (! $this->canChange())
            <p class="rounded-lg border border-stone-200 bg-white p-4 text-sm text-stone-600">
                {{ __('Changes are possible up to :hours hour(s) before the appointment. Please call the business if you need to change it now.', ['hours' => $noticeHours]) }}
            </p>
        @elseif ($choosingTime)
            <section>
                <h2 class="text-base font-semibold">{{ __('Pick a new time') }}</h2>
                <p class="mt-1 text-sm text-stone-600">{{ __('Same service and same staff member. Times are shown in :timezone.', ['timezone' => $tz]) }}</p>

                <div class="mt-3 max-w-xs">
                    <x-ui.label for="date">{{ __('Day') }}</x-ui.label>
                    <x-ui.input id="date" type="date" wire:model.live="date" min="{{ $minDate }}" max="{{ $maxDate }}" />
                </div>

                <div wire:loading.class="opacity-50" class="mt-4">
                    @if ($this->slots->isEmpty())
                        <p class="rounded-lg border border-stone-200 bg-stone-50 p-4 text-sm text-stone-600">{{ __('No times available on this day. Try another day.') }}</p>
                    @else
                        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6" dir="ltr">
                            @foreach ($this->slots as $slot)
                                <li>
                                    <button type="button" wire:click="chooseSlot('{{ $slot->start()->toIso8601String() }}')"
                                            class="w-full rounded-lg border border-stone-200 bg-white px-2 py-2 text-sm font-medium tabular-nums transition hover:border-teal-600 hover:bg-teal-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600">
                                        {{ $slot->start()->setTimezone($tz)->format('H:i') }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <button type="button" wire:click="stopReschedule" class="mt-5 text-sm font-medium text-stone-600 hover:text-stone-900">← {{ __('Back') }}</button>
            </section>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-stone-200 p-4">
                    <h2 class="text-sm font-semibold">{{ __('Need a different time?') }}</h2>
                    <p class="mt-1 text-sm text-stone-600">{{ __('Move this booking to another slot with the same staff member.') }}</p>
                    <x-ui.button type="button" wire:click="startReschedule" class="mt-3 w-auto">{{ __('Change time') }}</x-ui.button>
                </div>

                <form wire:submit="cancel" class="rounded-xl border border-stone-200 p-4">
                    <h2 class="text-sm font-semibold">{{ __('Can\'t make it?') }}</h2>
                    <div class="mt-2">
                        <x-ui.label for="reason">{{ __('Reason (optional)') }}</x-ui.label>
                        <x-ui.input id="reason" type="text" wire:model="reason" maxlength="500" />
                        <x-ui.error for="reason" />
                    </div>
                    <button type="submit" wire:confirm="{{ __('Cancel this booking?') }}"
                            class="mt-3 inline-flex w-auto items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                        {{ __('Cancel booking') }}
                    </button>
                </form>
            </div>
        @endif
    @endif
</div>
