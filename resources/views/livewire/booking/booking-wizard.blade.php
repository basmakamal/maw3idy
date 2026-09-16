@php
    $tz = $this->timezone();
    $service = $this->service;
    $steps = [1 => __('Service'), 2 => __('Staff'), 3 => __('Time'), 4 => __('Details')];
@endphp
<div class="space-y-6">
    {{-- Progress --}}
    <ol class="flex flex-wrap gap-2 text-xs font-medium" aria-label="{{ __('Booking steps') }}">
        @foreach ($steps as $number => $label)
            <li class="flex items-center gap-1.5 rounded-full px-3 py-1 {{ $number === $step ? 'bg-teal-700 text-white' : ($number < $step ? 'bg-teal-50 text-teal-800' : 'bg-stone-100 text-stone-500') }}"
                @if ($number === $step) aria-current="step" @endif>
                <span>{{ $number }}</span><span>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    @if ($slotNotice)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $slotNotice }}</div>
    @endif

    {{-- Step 1: service --}}
    @if ($step === 1)
        <section>
            <h2 class="text-lg font-semibold">{{ __('What would you like to book?') }}</h2>
            @if ($this->services->isEmpty())
                <p class="mt-4 text-sm text-stone-600">{{ __('This business has not published any services yet.') }}</p>
            @else
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($this->services as $option)
                        <li>
                            <button type="button" wire:click="chooseService({{ $option->id }})"
                                    class="flex w-full flex-col items-start rounded-xl border border-stone-200 bg-white p-4 text-start transition hover:border-teal-600 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600">
                                <span class="font-semibold">{{ $option->name }}</span>
                                <span class="mt-1 text-sm text-stone-600">
                                    {{ __(':minutes min', ['minutes' => $option->duration_minutes]) }} · {{ number_format((float) $option->price, 2) }} {{ config('booking.currency') }}
                                </span>
                                @if ($option->description)
                                    <span class="mt-2 text-sm text-stone-500">{{ $option->description }}</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-ui.error for="serviceId" />
        </section>
    @endif

    {{-- Step 2: staff --}}
    @if ($step === 2 && $service)
        <section>
            <h2 class="text-lg font-semibold">{{ __('Who would you like to see?') }}</h2>
            <p class="mt-1 text-sm text-stone-600">{{ $service->name }} · {{ __(':minutes min', ['minutes' => $service->duration_minutes]) }}</p>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                <li>
                    <button type="button" wire:click="chooseStaff(null)"
                            class="w-full rounded-xl border border-dashed border-stone-300 bg-white p-4 text-start font-semibold transition hover:border-teal-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600">
                        {{ __('Anyone available') }}
                        <span class="mt-1 block text-sm font-normal text-stone-600">{{ __('Shows the most times') }}</span>
                    </button>
                </li>
                @foreach ($this->staffOptions as $member)
                    <li>
                        <button type="button" wire:click="chooseStaff({{ $member->id }})"
                                class="w-full rounded-xl border border-stone-200 bg-white p-4 text-start font-semibold transition hover:border-teal-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600">
                            {{ $member->name }}
                        </button>
                    </li>
                @endforeach
            </ul>
            <x-ui.error for="staffId" />
            <button type="button" wire:click="back" class="mt-6 text-sm font-medium text-stone-600 hover:text-stone-900">← {{ __('Back') }}</button>
        </section>
    @endif

    {{-- Step 3: day and time --}}
    @if ($step === 3 && $service)
        <section>
            <h2 class="text-lg font-semibold">{{ __('Pick a day and time') }}</h2>
            <p class="mt-1 text-sm text-stone-600">
                {{ $service->name }} · {{ $this->staff?->name ?? __('Anyone available') }}
            </p>

            <div class="mt-4 max-w-xs">
                <x-ui.label for="date">{{ __('Day') }}</x-ui.label>
                <x-ui.input id="date" type="date" wire:model.live="date" min="{{ $minDate }}" max="{{ $maxDate }}" />
                <x-ui.error for="date" />
            </div>

            <p class="mt-4 text-xs text-stone-500">{{ __('Times are shown in :timezone.', ['timezone' => $tz]) }}</p>

            <div wire:loading.class="opacity-50" class="mt-2">
                @if ($this->slots->isEmpty())
                    <p class="rounded-lg border border-stone-200 bg-stone-50 p-4 text-sm text-stone-600">{{ __('No times available on this day. Try another day.') }}</p>
                @else
                    <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6" dir="ltr">
                        @foreach ($this->slots as $option)
                            <li>
                                <button type="button" wire:click="chooseSlot('{{ $option->start()->toIso8601String() }}')"
                                        class="w-full rounded-lg border border-stone-200 bg-white px-2 py-2 text-sm font-medium tabular-nums transition hover:border-teal-600 hover:bg-teal-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600">
                                    {{ $option->start()->setTimezone($tz)->format('H:i') }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <x-ui.error for="slot" />
            <button type="button" wire:click="back" class="mt-6 text-sm font-medium text-stone-600 hover:text-stone-900">← {{ __('Back') }}</button>
        </section>
    @endif

    {{-- Step 4: details --}}
    @if ($step === 4 && $service && $slot)
        @php $start = \Carbon\CarbonImmutable::parse($slot, 'UTC')->setTimezone($tz); @endphp
        <section>
            <h2 class="text-lg font-semibold">{{ __('Your details') }}</h2>

            <dl class="mt-4 grid gap-2 rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm sm:grid-cols-2">
                <div><dt class="text-stone-500">{{ __('Service') }}</dt><dd class="font-medium">{{ $service->name }}</dd></div>
                <div><dt class="text-stone-500">{{ __('With') }}</dt><dd class="font-medium">{{ $this->staff?->name ?? __('Anyone available') }}</dd></div>
                <div><dt class="text-stone-500">{{ __('When') }}</dt><dd class="font-medium">{{ $start->translatedFormat('l j F Y') }} · {{ $start->format('H:i') }} ({{ $tz }})</dd></div>
                <div><dt class="text-stone-500">{{ __('Price') }}</dt><dd class="font-medium">{{ number_format((float) $service->price, 2) }} {{ config('booking.currency') }}</dd></div>
            </dl>

            <form wire:submit="confirm" class="mt-6 space-y-4">
                <div>
                    <x-ui.label for="customerName">{{ __('Your name') }}</x-ui.label>
                    <x-ui.input id="customerName" type="text" wire:model="customerName" required maxlength="100" autocomplete="name" />
                    <x-ui.error for="customerName" />
                </div>
                <div>
                    <x-ui.label for="customerPhone">{{ __('Mobile number') }}</x-ui.label>
                    <x-ui.input id="customerPhone" type="tel" wire:model="customerPhone" required autocomplete="tel" dir="ltr" placeholder="05xxxxxxxx" />
                    <x-ui.error for="customerPhone" />
                </div>
                <div>
                    <x-ui.label for="customerEmail">{{ __('Email (optional)') }}</x-ui.label>
                    <x-ui.input id="customerEmail" type="email" wire:model="customerEmail" autocomplete="email" dir="ltr" />
                    <x-ui.error for="customerEmail" />
                </div>

                <div class="flex items-center justify-between gap-4 pt-2">
                    <button type="button" wire:click="back" class="text-sm font-medium text-stone-600 hover:text-stone-900">← {{ __('Back') }}</button>
                    <x-ui.button class="w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="confirm">{{ __('Confirm booking') }}</span>
                        <span wire:loading wire:target="confirm">{{ __('Booking…') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </section>
    @endif
</div>
