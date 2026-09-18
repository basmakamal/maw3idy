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
    @livewireStyles
</head>
<body class="min-h-full font-sans text-stone-900 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 py-8 sm:py-12">
        <header class="mb-8 text-center">
            <p class="text-2xl font-semibold tracking-tight text-teal-800">{{ tenant()->name }}</p>
            @if ($title)
                <h1 class="mt-1 text-sm text-stone-600">{{ $title }}</h1>
            @endif
        </header>

        <main class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
            {{ $slot }}
        </main>

        <footer class="mt-8 flex flex-col items-center gap-3 text-xs text-stone-400">
            <x-language-switcher route="tenant.locale" />
            <p>{{ __('Powered by') }} <a href="{{ route('central.home') }}" class="font-medium text-stone-500 hover:text-teal-800">{{ config('app.name') }}</a></p>
        </footer>
    </div>
    @livewireScripts
</body>
</html>
