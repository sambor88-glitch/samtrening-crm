@props([
    'title' => null,
])

{{-- One empty state per cause (empty roster, empty filter, empty archive…) — never a generic "no data". --}}
<div {{ $attributes->class('empty-state') }}>
    @if ($title)
        <p class="empty-state-title">{{ $title }}</p>
    @endif
    <p class="empty-state-text">{{ $slot }}</p>
    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
