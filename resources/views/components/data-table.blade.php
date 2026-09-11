@props([
    'columns' => [],
])

{{-- Every <td> in the slot needs data-label: below 760 px each row turns into a card that takes its
     labels from that attribute. Use data-label="" for the column with buttons. --}}
<div {{ $attributes->class('data-table') }}>
    <table class="table">
        @if ($columns)
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
