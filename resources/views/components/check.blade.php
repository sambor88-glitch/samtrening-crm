@props([
    'label' => null,
    'hint' => null,
])

{{-- A checkbox with its own explanation — consent and invoice switches both need one. --}}
<label {{ $attributes->only('class')->class('check') }}>
    <input type="checkbox" {{ $attributes->except('class') }}>
    <span class="box"></span>
    <span>
        <span class="check-title">{{ $label }}</span>
        @if ($hint)
            <span class="check-hint">{{ $hint }}</span>
        @endif
    </span>
</label>
