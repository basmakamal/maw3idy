@php $grouped = $this->bookings; @endphp
<div class="space-y-5">
    @if ($notice)
        <div class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">{{ $notice }}</div>
    @endif
    @if ($problem)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $problem }}</div>
    @endif

    {{-- Controls --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg border border-stone-300 bg-white p-0.5" role="group" aria-label="{{ __('View') }}">
                <button type="button" wire:click="showDay"
                        class="rounded-md px-3 py-1.5 text-sm font-medium {{ $mode === 'day' ? 'bg-teal-700 text-white' : 'text-stone-600 hover:text-stone-900' }}">{{ __('Day') }}</button>
                <button type="button" wire:click="showWeek"
                        class="rounded-md px-3 py-1.5 text-sm font-medium {{ $mode === 'week' ? 'bg-teal-700 text-white' : 'text-stone-600 hover:text-stone-900' }}">{{ __('Week') }}</button>
            </div>

            <div class="inline-flex items-center gap-1">
                <button type="button" wire:click="previous" aria-label="{{ __('Previous') }}"
                        class="rounded-lg border border-stone-300 bg-white px-2.5 py-1.5 text-sm text-stone-600 hover:text-stone-900">&larr;</button>
                <button type="button" wire:click="goToToday"
                        class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 hover:text-stone-900">{{ __('Today') }}</button>
                <button type="button" wire:click="next" aria-label="{{ __('Next') }}"
                        class="rounded-lg border border-stone-300 bg-white px-2.5 py-1.5 text-sm text-stone-600 hover:text-stone-900">&rarr;</button>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <x-ui.label for="cal-date">{{ __('Jump to') }}</x-ui.label>
                <x-ui.input id="cal-date" type="date" wire:model.live="date" class="w-auto" />
            </div>
            <div>
                <x-ui.label for="cal-staff">{{ __('Staff') }}</x-ui.label>
                <x-ui.select id="cal-staff" wire:model.live="staffId" class="w-auto">
                    <option value="">{{ __('Everyone') }}</option>
                    @foreach ($this->staffOptions as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold">{{ $this->heading() }}</h2>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" wire:model.live="includeCancelled" class="size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-600">
            {{ __('Show cancelled') }}
        </label>
    </div>

    <p class="text-xs text-stone-500">{{ __('Times are shown in :timezone.', ['timezone' => $timezone]) }}</p>

    @if ($mode === 'day')
        @php $dayBookings = $grouped->get($days[0]->toDateString(), collect()); @endphp
        <div wire:loading.class="opacity-50">
            @if ($dayBookings->isEmpty())
                <x-empty-state :title="__('Nothing scheduled')" :description="__('No bookings on this day. Try another day, or share your booking page.')" />
            @else
                <ul class="divide-y divide-stone-100 overflow-hidden rounded-2xl border border-stone-200 bg-white">
                    @foreach ($dayBookings as $booking)
                        <li wire:key="booking-{{ $booking->id }}" class="flex flex-wrap items-start gap-4 p-4 {{ $booking->isConfirmed() ? '' : 'bg-stone-50' }}">
                            <p class="w-28 shrink-0 text-sm font-semibold tabular-nums" dir="ltr">
                                {{ $booking->starts_at->setTimezone($timezone)->format('H:i') }}–{{ $booking->ends_at->setTimezone($timezone)->format('H:i') }}
                            </p>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $booking->service->name }} · {{ $booking->staff->name }}</p>
                                <p class="text-sm text-stone-600">
                                    {{ $booking->customer_name }} ·
                                    <a href="tel:{{ $booking->customer_phone }}" class="hover:text-teal-800" dir="ltr">{{ $booking->customer_phone }}</a>
                                </p>
                                <p class="mt-1 text-xs text-stone-500" dir="ltr">
                                    {{ $booking->reference }} · {{ number_format((float) $booking->price, 2) }} {{ $currency }}
                                    @if ($booking->buffer_after_minutes)
                                        · {{ __('+:minutes min buffer', ['minutes' => $booking->buffer_after_minutes]) }}
                                    @endif
                                </p>
                                @if (! $booking->isConfirmed() && $booking->cancellation_reason)
                                    <p class="mt-1 text-xs text-stone-500">{{ __('Reason: :reason', ['reason' => $booking->cancellation_reason]) }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $booking->isConfirmed() ? 'bg-teal-50 text-teal-800' : 'bg-stone-200 text-stone-600' }}">
                                    {{ $booking->status->label() }}
                                </span>
                                @if ($booking->isConfirmed() && $booking->starts_at->isFuture())
                                    <button type="button" wire:click="cancel({{ $booking->id }})" wire:confirm="{{ __('Cancel this booking and tell the customer?') }}"
                                            class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('Cancel') }}</button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <div class="overflow-x-auto" wire:loading.class="opacity-50">
            <div class="grid min-w-[56rem] grid-cols-7 gap-2">
                @foreach ($days as $day)
                    @php $dayBookings = $grouped->get($day->toDateString(), collect()); @endphp
                    <div class="rounded-xl border {{ $this->isToday($day) ? 'border-teal-500 bg-teal-50/40' : 'border-stone-200 bg-white' }}">
                        <button type="button" wire:click="showDay('{{ $day->toDateString() }}')"
                                class="w-full border-b border-stone-200 px-3 py-2 text-start hover:bg-stone-50">
                            <span class="block text-xs font-medium uppercase tracking-wide text-stone-500">{{ $day->translatedFormat('D') }}</span>
                            <span class="block text-sm font-semibold">{{ $day->translatedFormat('j M') }}</span>
                        </button>

                        <ul class="space-y-1 p-2">
                            @forelse ($dayBookings as $booking)
                                <li wire:key="week-booking-{{ $booking->id }}"
                                    class="rounded-lg border p-2 text-xs {{ $booking->isConfirmed() ? 'border-teal-100 bg-teal-50/60' : 'border-stone-200 bg-stone-50 line-through decoration-stone-400' }}">
                                    <p class="font-semibold tabular-nums" dir="ltr">{{ $booking->starts_at->setTimezone($timezone)->format('H:i') }}</p>
                                    <p class="truncate font-medium">{{ $booking->service->name }}</p>
                                    <p class="truncate text-stone-600">{{ $booking->customer_name }}</p>
                                    <p class="truncate text-stone-500">{{ $booking->staff->name }}</p>
                                </li>
                            @empty
                                <li class="px-1 py-2 text-xs text-stone-400">{{ __('Free') }}</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
