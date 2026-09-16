<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-600">{{ __('What customers can book, how long it takes and what it costs.') }}</p>
        @if ($canManage && ! $editing)
            <x-ui.button type="button" wire:click="create" class="w-auto">{{ __('Add service') }}</x-ui.button>
        @endif
    </div>

    @if ($editing)
        <form wire:submit="save" class="space-y-5 rounded-2xl border border-teal-200 bg-white p-6">
            <h2 class="text-base font-semibold">{{ $form->service ? __('Edit service') : __('New service') }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.label for="name">{{ __('Name') }}</x-ui.label>
                    <x-ui.input id="name" type="text" wire:model="form.name" required maxlength="100" />
                    <x-ui.error for="form.name" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.label for="description">{{ __('Description (optional)') }}</x-ui.label>
                    <x-ui.input id="description" type="text" wire:model="form.description" maxlength="500" />
                    <x-ui.error for="form.description" />
                </div>
                <div>
                    <x-ui.label for="duration_minutes">{{ __('Duration (minutes)') }}</x-ui.label>
                    <x-ui.input id="duration_minutes" type="number" wire:model="form.duration_minutes" min="5" max="480" step="5" required dir="ltr" />
                    <x-ui.error for="form.duration_minutes" />
                </div>
                <div>
                    <x-ui.label for="buffer_after_minutes">{{ __('Buffer after (minutes)') }}</x-ui.label>
                    <x-ui.input id="buffer_after_minutes" type="number" wire:model="form.buffer_after_minutes" min="0" max="240" step="5" required dir="ltr" />
                    <x-ui.error for="form.buffer_after_minutes" />
                    <p class="mt-1 text-xs text-stone-500">{{ __('Clean-up time during which the staff member cannot be booked.') }}</p>
                </div>
                <div>
                    <x-ui.label for="price">{{ __('Price (:currency)', ['currency' => $currency]) }}</x-ui.label>
                    <x-ui.input id="price" type="number" wire:model="form.price" min="0" step="0.01" required dir="ltr" />
                    <x-ui.error for="form.price" />
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="form.active" class="size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-600">
                        {{ __('Bookable by customers') }}
                    </label>
                </div>
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-stone-700">{{ __('Offered by') }}</legend>
                @if ($this->staffOptions->isEmpty())
                    <p class="mt-1 text-sm text-stone-500">{{ __('Add staff first, then assign them here.') }}</p>
                @else
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                        @foreach ($this->staffOptions as $member)
                            <label class="flex items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 text-sm">
                                <input type="checkbox" value="{{ $member->id }}" wire:model="form.staffIds" class="size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-600">
                                <span class="{{ $member->active ? '' : 'text-stone-400' }}">{{ $member->name }}@if (! $member->active) ({{ __('inactive') }})@endif</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <x-ui.error for="form.staffIds" />
                <x-ui.error for="form.staffIds.*" />
            </fieldset>

            <div class="flex items-center justify-end gap-3">
                <button type="button" wire:click="cancel" class="text-sm font-medium text-stone-600 hover:text-stone-900">{{ __('Cancel') }}</button>
                <x-ui.button class="w-auto" wire:loading.attr="disabled">{{ __('Save') }}</x-ui.button>
            </div>
        </form>
    @endif

    @if ($this->services->isEmpty())
        <x-empty-state :title="__('No services yet')" :description="__('Services are what customers book: a haircut, a consultation, a lesson. Each has a duration, a price and an optional buffer afterwards.')" />
    @else
        <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50 text-start text-xs uppercase tracking-wide text-stone-500">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('Service') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('Duration') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('Price') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('Offered by') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                        @if ($canManage)<th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($this->services as $service)
                        <tr wire:key="service-{{ $service->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $service->name }}</p>
                                @if ($service->description)<p class="text-xs text-stone-500">{{ $service->description }}</p>@endif
                            </td>
                            <td class="px-4 py-3 tabular-nums" dir="ltr">
                                {{ __(':minutes min', ['minutes' => $service->duration_minutes]) }}
                                @if ($service->buffer_after_minutes)<span class="text-stone-400"> +{{ $service->buffer_after_minutes }}</span>@endif
                            </td>
                            <td class="px-4 py-3 tabular-nums" dir="ltr">{{ number_format((float) $service->price, 2) }} {{ $currency }}</td>
                            <td class="px-4 py-3 text-stone-600">{{ $service->staff->pluck('name')->join(', ') ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $service->active ? 'bg-teal-50 text-teal-800' : 'bg-stone-100 text-stone-500' }}">
                                    {{ $service->active ? __('Active') : __('Hidden') }}
                                </span>
                            </td>
                            @if ($canManage)
                                <td class="px-4 py-3 text-end whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $service->id }})" class="text-sm font-medium text-teal-700 hover:text-teal-900">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="toggleActive({{ $service->id }})" class="ms-3 text-sm font-medium text-stone-600 hover:text-stone-900">{{ $service->active ? __('Hide') : __('Show') }}</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
