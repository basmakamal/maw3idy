@props(['route'])
@php $current = app()->getLocale(); @endphp
<form method="POST" action="{{ route($route) }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }}>
    @csrf
    <span class="sr-only">{{ __('Language') }}</span>
    @foreach (\App\Support\Localization::supported() as $locale)
        <button type="submit" name="locale" value="{{ $locale }}"
                @if ($locale === $current) aria-current="true" @endif
                lang="{{ $locale }}"
                class="rounded-md px-2 py-1 text-xs font-medium transition {{ $locale === $current ? 'bg-stone-200 text-stone-900' : 'text-stone-500 hover:text-stone-900' }}">
            {{ \App\Support\Localization::name($locale) }}
        </button>
    @endforeach
</form>
