@props(['for'])
@error($for)
    <p {{ $attributes->merge(['class' => 'mt-1 text-sm text-red-600']) }} role="alert">{{ $message }}</p>
@enderror
