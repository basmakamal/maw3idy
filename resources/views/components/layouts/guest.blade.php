@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction(app()->getLocale()) }}" class="h-full bg-stone-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|tajawal:400,500,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans text-stone-900 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-12">
        <a href="{{ route('central.home') }}" class="mb-8 self-center text-2xl font-semibold tracking-tight text-teal-800">
            {{ config('app.name') }}
        </a>

        <main class="rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
