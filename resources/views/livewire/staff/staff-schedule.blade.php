<div class="space-y-8">
    @if (session('status'))
        <div class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">{{ session('status') }}</div>
    @endif

    <section class="rounded-2xl border border-stone-200 bg-white p-6">
        <h2 class="text-base font-semibold">{{ __('Weekly hours') }}</h2>
        <p class="mt-1 text-sm text-stone-600">{{ __('Times are in :timezone. Customers can only book inside these hours.', ['timezone' => $timezone]) }}</p>

        <form wire:submit="saveHours" class="mt-5 space-y-3">
            @foreach ($weekdays as $weekday)
                @php $key = $weekday->value; @endphp
                <div class="grid items-center gap-3 sm:grid-cols-[8rem_auto_1fr] {{ $days[$key]['working'] ? '' : 'text-stone-400' }}" wire:key="day-{{ $key }}">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" wire:model.live="days.{{ $key }}.working" class="size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-600" @disabled(! $canManage)>
                        {{ $weekday->label() }}
                    </label>
                    <div class="flex items-center gap-2" dir="ltr">
                        <input type="time" wire:model="days.{{ $key }}.start" step="300" aria-label="{{ __(':day start', ['day' => $weekday->label()]) }}"
                               class="rounded-lg border border-stone-300 px-2 py-1.5 text-sm tabular-nums focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 disabled:bg-stone-50"
                               @disabled(! $canManage || ! $days[$key]['working'])>
                        <span>–</span>
                        <input type="time" wire:model="days.{{ $key }}.end" step="300" aria-label="{{ __(':day end', ['day' => $weekday->label()]) }}"
                               class="rounded-lg border border-stone-300 px-2 py-1.5 text-sm tabular-nums focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 disabled:bg-stone-50"
                               @disabled(! $canManage || ! $days[$key]['working'])>
                    </div>
                    <div><x-ui.error for="days.{{ $key }}.end" class="mt-0" /></div>
                </div>
            @endforeach

            @if ($canManage)
                <div class="flex justify-end pt-2">
                    <x-ui.button class="w-auto" wire:loading.attr="disabled">{{ __('Save hours') }}</x-ui.button>
                </div>
            @endif
        </form>
    </section>

    <section class="rounded-2xl border border-stone-200 bg-white p-6">
        <h2 class="text-base font-semibold">{{ __('Time off') }}</h2>
        <p class="mt-1 text-sm text-stone-600">{{ __('Holidays, sick days, a long lunch: nothing can be booked during these.') }}</p>

        @if ($canManage)
            <form wire:submit="addTimeOff" class="mt-5 grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                <div>
                    <x-ui.label for="offStart">{{ __('From') }}</x-ui.label>
                    <x-ui.input id="offStart" type="datetime-local" wire:model="offStart" step="900" dir="ltr" />
                    <x-ui.error for="offStart" />
                </div>
                <div>
                    <x-ui.label for="offEnd">{{ __('Until') }}</x-ui.label>
                    <x-ui.input id="offEnd" type="datetime-local" wire:model="offEnd" step="900" dir="ltr" />
                    <x-ui.error for="offEnd" />
                </div>
                <div>
                    <x-ui.label for="offReason">{{ __('Reason (optional)') }}</x-ui.label>
                    <x-ui.input id="offReason" type="text" wire:model="offReason" maxlength="200" />
                    <x-ui.error for="offReason" />
                </div>
                <x-ui.button class="w-auto" wire:loading.attr="disabled">{{ __('Add') }}</x-ui.button>
            </form>
        @endif

        <ul class="mt-5 divide-y divide-stone-100 text-sm">
            @forelse ($this->upcomingTimeOff as $absence)
                <li wire:key="off-{{ $absence->id }}" class="flex items-center justify-between gap-3 py-2">
                    <div>
                        <p class="font-medium" dir="ltr">
                            {{ $absence->starts_at->setTimezone($timezone)->translatedFormat('D j M Y H:i') }}
                            → {{ $absence->ends_at->setTimezone($timezone)->translatedFormat('D j M Y H:i') }}
                        </p>
                        @if ($absence->reason)<p class="text-xs text-stone-500">{{ $absence->reason }}</p>@endif
                    </div>
                    @if ($canManage)
                        <button type="button" wire:click="removeTimeOff({{ $absence->id }})" wire:confirm="{{ __('Remove this time off?') }}" class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('Remove') }}</button>
                    @endif
                </li>
            @empty
                <li class="py-2 text-stone-500">{{ __('No upcoming time off.') }}</li>
            @endforelse
        </ul>
    </section>
</div>
