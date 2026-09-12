@props([
    'name' => null,
    'label' => null,
    'hint' => null,
    'options' => [],   // value => label
    'value' => null,
    'placeholder' => null,
])

@php
    $id = $attributes->get('id', $name);
    $control = $attributes->except('id')->merge(['id' => $id, 'name' => $name])->class('input');
@endphp

<div class="field">
    @if ($label)
        <label for="{{ $id }}">{{ $label }}</label>
    @endif

    <select {{ $control }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
