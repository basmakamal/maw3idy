@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction(app()->getLocale()) }}" class="h-full bg-stone-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ tenant()->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|tajawal:400,500,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles(['nonce' => $cspNonce])
</head>
<body class="min-h-full font-sans text-stone-900 antialiased">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-e border-stone-200 bg-white md:flex">
            <div class="flex h-16 items-center px-6 text-lg font-semibold tracking-tight text-teal-800">
                {{ config('app.name') }}
            </div>
            <x-main-nav class="flex-1 space-y-1 px-3 py-4" />
            <div class="border-t border-stone-200 p-4 text-xs text-stone-500" dir="ltr">{{ tenant()->domain() }}</div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between gap-4 border-b border-stone-200 bg-white px-4 sm:px-6">
                <div class="min-w-0">
                    <h1 class="truncate text-base font-semibold">{{ $title ?? tenant()->name }}</h1>
                    @if ($title)
                        <p class="truncate text-xs text-stone-500">{{ tenant()->name }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-4">
                    <x-language-switcher route="tenant.locale" class="hidden sm:inline-flex" />
                    <span class="hidden text-sm text-stone-600 sm:inline">{{ auth()->user()?->name }}</span>
                    <form method="POST" action="{{ route('tenant.logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-stone-600 hover:text-stone-900">{{ __('Sign out') }}</button>
                    </form>
                </div>
            </header>

            <x-main-nav class="flex gap-1 overflow-x-auto border-b border-stone-200 bg-white px-2 py-2 md:hidden" />

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts(['nonce' => $cspNonce])
</body>
</html>
