@props([
    'variant' => 'neutral', // accent | accent-2 | neutral | outline
])

<span {{ $attributes->class('tag tag-'.$variant) }}>{{ $slot }}</span>
