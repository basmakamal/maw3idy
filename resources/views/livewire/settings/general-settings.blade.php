<form wire:submit="save" class="space-y-5">
    @if (session('status'))
        <div class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">{{ session('status') }}</div>
    @endif

    <div>
        <x-ui.label for="name">{{ __('Business name') }}</x-ui.label>
        <x-ui.input id="name" type="text" wire:model="name" required maxlength="100" />
        <x-ui.error for="name" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-ui.label for="timezone">{{ __('Timezone') }}</x-ui.label>
            <x-ui.select id="timezone" wire:model="timezone" required>
                @foreach ($timezones as $tz)
                    <option value="{{ $tz }}">{{ $tz }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.error for="timezone" />
            <p class="mt-1 text-xs text-stone-500">{{ __('Schedules and bookings are shown in this timezone.') }}</p>
        </div>

        <div>
            <x-ui.label for="locale">{{ __('Language') }}</x-ui.label>
            <x-ui.select id="locale" wire:model="locale" required>
                @foreach ($locales as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.error for="locale" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <span wire:loading class="text-sm text-stone-500">{{ __('Saving…') }}</span>
        <x-ui.button class="w-auto" wire:loading.attr="disabled">{{ __('Save changes') }}</x-ui.button>
    </div>
</form>
