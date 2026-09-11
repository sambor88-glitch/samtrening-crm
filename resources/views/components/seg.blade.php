@props([
    'name',
    'options' => [], // value => label
    'value' => null,
])

{{-- Extra attributes (e.g. wire:model.live) go to every radio input. --}}
<div {{ $attributes->only('class')->class('seg') }} role="radiogroup">
    @foreach ($options as $optionValue => $optionLabel)
        <label class="seg-opt">
            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" @checked((string) $value === (string) $optionValue) {{ $attributes->except('class') }}>
            <span>{{ $optionLabel }}</span>
        </label>
    @endforeach
</div>
