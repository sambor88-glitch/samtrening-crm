@props([
    'rows' => 6,
    'height' => 52, // the height of the real row, so the layout does not jump when data arrives
])

<div {{ $attributes->class('skeleton-rows') }} aria-busy="true">
    <span class="sr-only">Wczytuję…</span>
    @for ($i = 0; $i < $rows; $i++)
        <div class="skeleton-row" style="height: {{ (int) $height }}px"></div>
    @endfor
</div>
