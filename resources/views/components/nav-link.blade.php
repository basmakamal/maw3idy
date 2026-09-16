@props(['href', 'active' => false])
<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'flex items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition',
       'bg-teal-50 text-teal-800' => $active,
       'text-stone-600 hover:bg-stone-100 hover:text-stone-900' => ! $active,
   ]) }}>
    {{ $slot }}
</a>
