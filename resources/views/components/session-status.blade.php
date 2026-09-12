@props([
    'session',
])

@php
    use App\Domain\Training\Enums\PaymentStatus;
    use App\Domain\Training\Enums\SessionKind;

    // A cancellation says whether it was charged; a session held says how it was settled.
    [$label, $variant] = match (true) {
        $session->kind !== SessionKind::Completed => [
            ($session->kind === SessionKind::Cancelled ? 'Odwołana' : 'Nieobecność')
                .($session->payment_status === PaymentStatus::Waived ? ' · 0 zł' : ' · naliczone'),
            $session->payment_status === PaymentStatus::Waived ? 'neutral' : 'accent',
        ],
        $session->payment_status === PaymentStatus::Paid => ['Zapłacone', 'neutral'],
        $session->payment_status === PaymentStatus::Requested => ['Poproszono', 'outline'],
        $session->payment_status === PaymentStatus::Waived => ['Nie naliczono', 'neutral'],
        default => ['Na saldzie', 'accent'],
    };
@endphp

<x-tag :variant="$variant" {{ $attributes }}>{{ $label }}</x-tag>
