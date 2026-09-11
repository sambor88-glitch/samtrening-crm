@props([
    'variant' => 'secondary', // primary | secondary | ghost
    'block' => false,
    'href' => null,
    'type' => 'button',
])

@php
    $classes = 'btn btn-'.$variant.($block ? ' btn-block' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
