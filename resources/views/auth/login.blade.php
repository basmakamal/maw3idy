<x-layouts.guest :title="__('Sign in')">
    <h1 class="text-xl font-semibold">{{ __('Sign in to :business', ['business' => tenant()->name]) }}</h1>
    <p class="mt-1 text-sm text-stone-500" dir="ltr">{{ tenant()->domain() }}</p>

    @if (request()->boolean('registered'))
        <div class="mt-4 rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">
            {{ __('Your workspace is ready. Sign in with the account you just created.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('tenant.login.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-ui.label for="email">{{ __('Email') }}</x-ui.label>
            <x-ui.input id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-ui.error for="email" />
        </div>

        <div>
            <x-ui.label for="password">{{ __('Password') }}</x-ui.label>
            <x-ui.input id="password" name="password" type="password" required autocomplete="current-password" />
            <x-ui.error for="password" />
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-700">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-600">
            {{ __('Remember me') }}
        </label>

        <x-ui.button>{{ __('Sign in') }}</x-ui.button>
    </form>
</x-layouts.guest>
