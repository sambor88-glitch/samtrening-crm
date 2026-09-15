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
                .match ($session->payment_status) {
                    PaymentStatus::Waived => ' · 0 zł',
                    PaymentStatus::Prepaid => ' · z przedpłaty',
                    default => ' · naliczone',
                },
            in_array($session->payment_status, [PaymentStatus::Waived, PaymentStatus::Prepaid], true) ? 'neutral' : 'accent',
        ],
        $session->payment_status === PaymentStatus::Paid => ['Zapłacone', 'neutral'],
        $session->payment_status === PaymentStatus::Prepaid => ['Z przedpłaty', 'neutral'],
        $session->payment_status === PaymentStatus::Requested => ['Poproszono', 'outline'],
        $session->payment_status === PaymentStatus::Waived => ['Nie naliczono', 'neutral'],
        default => ['Na saldzie', 'accent'],
    };
@endphp

<x-tag :variant="$variant" {{ $attributes }}>{{ $label }}</x-tag>
