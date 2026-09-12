@props([
    'label' => null,
])

{{-- One block of the client card: a lime label and whatever belongs under it. --}}
<div {{ $attributes->class('border-l border-divider px-[18px] py-[22px]') }}>
    <p class="mb-2.5 text-[10px] tracking-[0.14em] text-accent uppercase">{{ $label }}</p>
    {{ $slot }}
</div>
