@php
    $text = 'Trening personalny ● Zdrowa ciąża ● E-trening online ● Studio 1:1 ● Plac Na Groblach ● Płacisz za odbyte sesje ● ';
@endphp

{{-- Decorative, so hidden from screen readers. The text runs twice for a seamless loop; the only
     animation in the product, and it stops for people who ask for reduced motion. --}}
<div class="overflow-hidden border-b-2 border-divider bg-surface" aria-hidden="true">
    <div class="flex w-max animate-ticker py-1.5 text-[11px] tracking-[0.16em] whitespace-pre uppercase opacity-[0.62] motion-reduce:animate-none">
        <span>{{ $text }}</span><span>{{ $text }}</span>
    </div>
</div>
