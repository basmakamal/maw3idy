@props(['for'])
<label for="{{ $for }}" {{ $attributes->merge(['class' => 'block text-sm font-medium text-stone-700']) }}>
    {{ $slot }}
</label>
