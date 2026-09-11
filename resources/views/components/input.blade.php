@props([
    'name' => null,
    'label' => null,
    'hint' => null, // shown under the field, so the label stays on one line
    'type' => 'text',
    'multiline' => false,
])

@php
    $id = $attributes->get('id', $name);
    $control = $attributes->except('id')->merge(['id' => $id, 'name' => $name])->class('input');
@endphp

<div class="field">
    @if ($label)
        <label for="{{ $id }}">{{ $label }}</label>
    @endif

    @if ($multiline)
        <textarea {{ $control }}>{{ $slot }}</textarea>
    @else
        <input type="{{ $type }}" {{ $control }}>
    @endif

    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
