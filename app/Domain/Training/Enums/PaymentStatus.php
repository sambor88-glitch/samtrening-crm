<?php

namespace App\Domain\Training\Enums;

/**
 * Where the money for a session stands. `Requested` means the client got a payment request —
 * it is still owed, so it keeps counting towards the balance. `Prepaid` means the money the client
 * paid up front covered it; only Billing\PrepaymentPool sets it, never a form.
 */
enum PaymentStatus: string
{
    case Paid = 'paid';
    case Balance = 'balance';
    case Requested = 'requested';
    case Waived = 'waived';
    case Prepaid = 'prepaid';

    /**
     * Settled one way or the other: paid, paid out of a prepayment, or written off by the trainer.
     *
     * @var list<self>
     */
    public const array SETTLED = [self::Paid, self::Prepaid, self::Waived];

    /**
     * Money the client still owes — docs/START-TUTAJ.md §6.
     */
    public function isPayable(): bool
    {
        return ! in_array($this, self::SETTLED, true);
    }

    /**
     * See SessionKind::label() — the same vocabulary, in one place.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Zapłacone',
            self::Balance => 'Na saldzie',
            self::Requested => 'Poproszono',
            self::Waived => 'Nie naliczono',
            self::Prepaid => 'Z przedpłaty',
        };
    }
}
