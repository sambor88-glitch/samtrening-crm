@props([
    'items' => [], // [['label' => …, 'value' => …, 'hint' => …], …] — the hint is optional
])

<dl {{ $attributes->class('stat-bar') }}>
    @foreach ($items as $item)
        <div class="stat-bar-cell">
            <dt class="stat-bar-label">{{ $item['label'] }}</dt>
            <dd class="stat-bar-value">{{ $item['value'] }}</dd>
            @if (filled($item['hint'] ?? null))
                <dd class="stat-bar-hint">{{ $item['hint'] }}</dd>
            @endif
        </div>
    @endforeach
</dl>
