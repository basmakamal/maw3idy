<x-layouts.guest :title="__('Create your booking page')">
    <h1 class="text-xl font-semibold">{{ __('Create your booking page') }}</h1>
    <p class="mt-1 text-sm text-stone-600">{{ __('Your business gets its own address; you get the owner account.') }}</p>

    <form method="POST" action="{{ route('central.register.store') }}" class="mt-6 space-y-5">
        @csrf

        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-stone-900">{{ __('Business') }}</legend>

            <div>
                <x-ui.label for="business_name">{{ __('Business name') }}</x-ui.label>
                <x-ui.input id="business_name" name="business_name" type="text" :value="old('business_name')" required autofocus maxlength="100" />
                <x-ui.error for="business_name" />
            </div>

            <div>
                <x-ui.label for="slug">{{ __('Booking page address') }}</x-ui.label>
                <div class="mt-1 flex rounded-lg border border-stone-300 shadow-xs focus-within:border-teal-600 focus-within:ring-2 focus-within:ring-teal-600/20" dir="ltr">
                    <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required minlength="3" maxlength="63"
                           pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?" autocapitalize="none" spellcheck="false"
                           class="block w-full min-w-0 rounded-s-lg border-0 bg-transparent px-3 py-2 text-sm focus:outline-none">
                    <span class="inline-flex items-center rounded-e-lg border-s border-stone-300 bg-stone-50 px-3 text-sm text-stone-500">.{{ config('tenancy.central_domain') }}</span>
                </div>
                <x-ui.error for="slug" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-ui.label for="timezone">{{ __('Timezone') }}</x-ui.label>
                    <x-ui.select id="timezone" name="timezone" required>
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $defaultTimezone) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.error for="timezone" />
                </div>

                <div>
                    <x-ui.label for="locale">{{ __('Language') }}</x-ui.label>
                    <x-ui.select id="locale" name="locale" required>
                        @foreach ($locales as $code => $label)
                            <option value="{{ $code }}" @selected(old('locale', app()->getLocale()) === $code)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.error for="locale" />
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-4 border-t border-stone-200 pt-5">
            <legend class="text-sm font-semibold text-stone-900">{{ __('Owner account') }}</legend>

            <div>
                <x-ui.label for="name">{{ __('Your name') }}</x-ui.label>
                <x-ui.input id="name" name="name" type="text" :value="old('name')" required maxlength="100" autocomplete="name" />
                <x-ui.error for="name" />
            </div>

            <div>
                <x-ui.label for="email">{{ __('Email') }}</x-ui.label>
                <x-ui.input id="email" name="email" type="email" :value="old('email')" required autocomplete="email" />
                <x-ui.error for="email" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-ui.label for="password">{{ __('Password') }}</x-ui.label>
                    <x-ui.input id="password" name="password" type="password" required autocomplete="new-password" />
                    <x-ui.error for="password" />
                </div>
                <div>
                    <x-ui.label for="password_confirmation">{{ __('Confirm password') }}</x-ui.label>
                    <x-ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                </div>
            </div>
        </fieldset>

        <x-ui.button>{{ __('Create account') }}</x-ui.button>
    </form>
</x-layouts.guest>
