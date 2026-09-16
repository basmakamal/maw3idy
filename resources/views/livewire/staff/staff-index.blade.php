<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-600">{{ __('Who delivers your services. Each staff member has their own weekly hours and time off.') }}</p>
        @if ($canManage && ! $editing)
            <x-ui.button type="button" wire:click="create" class="w-auto">{{ __('Add staff member') }}</x-ui.button>
        @endif
    </div>

    @if ($editing)
        <form wire:submit="save" class="space-y-5 rounded-2xl border border-teal-200 bg-white p-6">
            <h2 class="text-base font-semibold">{{ $form->staff ? __('Edit staff member') : __('New staff member') }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-ui.label for="staff_name">{{ __('Name') }}</x-ui.label>
                    <x-ui.input id="staff_name" type="text" wire:model="form.name" required maxlength="100" />
                    <x-ui.error for="form.name" />
                </div>
                <div>
                    <x-ui.label for="staff_email">{{ __('Email (optional)') }}</x-ui.label>
                    <x-ui.input id="staff_email" type="email" wire:model="form.email" dir="ltr" />
                    <x-ui.error for="form.email" />
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="form.active" class="size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-600">
                        {{ __('Accepting bookings') }}
                    </label>
                </div>
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-stone-700">{{ __('Services offered') }}</legend>
                @if ($this->serviceOptions->isEmpty())
                    <p class="mt-1 text-sm text-stone-500">{{ __('Add services first, then assign them here.') }}</p>
                @else
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                        @foreach ($this->serviceOptions as $service)
                            <label class="flex items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 text-sm">
                                <input type="checkbox" value="{{ $service->id }}" wire:model="form.serviceIds" class="size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-600">
                                <span>{{ $service->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <x-ui.error for="form.serviceIds" />
                <x-ui.error for="form.serviceIds.*" />
            </fieldset>

            <div class="flex items-center justify-end gap-3">
                <button type="button" wire:click="cancel" class="text-sm font-medium text-stone-600 hover:text-stone-900">{{ __('Cancel') }}</button>
                <x-ui.button class="w-auto" wire:loading.attr="disabled">{{ __('Save') }}</x-ui.button>
            </div>
        </form>
    @endif

    @if ($this->members->isEmpty())
        <x-empty-state :title="__('No staff yet')" :description="__('Staff members deliver services and have their own weekly hours and time off. Availability is computed from these.')" />
    @else
        <ul class="grid gap-3 md:grid-cols-2">
            @foreach ($this->members as $member)
                <li wire:key="staff-{{ $member->id }}" class="flex flex-col rounded-2xl border border-stone-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $member->name }}</p>
                            @if ($member->email)<p class="truncate text-xs text-stone-500" dir="ltr">{{ $member->email }}</p>@endif
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $member->active ? 'bg-teal-50 text-teal-800' : 'bg-stone-100 text-stone-500' }}">
                            {{ $member->active ? __('Active') : __('Inactive') }}
                        </span>
                    </div>

                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-stone-500">{{ __('Services') }}</dt><dd>{{ $member->services->pluck('name')->join(', ') ?: '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-stone-500">{{ __('Hours') }}</dt>
                            <dd>{{ $member->schedules->isEmpty() ? __('Not set') : trans_choice(':count day|:count days', $member->schedules->pluck('weekday')->unique()->count()) }}</dd></div>
                    </dl>

                    <div class="mt-4 flex items-center gap-4 text-sm font-medium">
                        <a href="{{ route('tenant.staff.schedule', $member) }}" class="text-teal-700 hover:text-teal-900">{{ __('Hours & time off') }}</a>
                        @if ($canManage)
                            <button type="button" wire:click="edit({{ $member->id }})" class="text-stone-600 hover:text-stone-900">{{ __('Edit') }}</button>
                            <button type="button" wire:click="toggleActive({{ $member->id }})" class="text-stone-600 hover:text-stone-900">{{ $member->active ? __('Deactivate') : __('Activate') }}</button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
