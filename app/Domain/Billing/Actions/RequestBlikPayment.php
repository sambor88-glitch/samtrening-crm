<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendPaymentRequest;
use App\Domain\Messaging\SmsCount;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;

/**
 * Asks the client to pay and marks those sessions as asked-about — in that order. If the message
 * cannot go out, the statuses stay where they were: "Poproszono" has to mean somebody was
 * actually asked.
 */
class RequestBlikPayment
{
    public function __construct(private readonly SendPaymentRequest $request) {}

    public function handle(User $trainer, Client $client): SmsCount
    {
        $count = $this->request->handle($trainer, $client);

        $client->sessions()
            ->where('payment_status', PaymentStatus::Balance)
            ->update(['payment_status' => PaymentStatus::Requested]);

        return $count;
    }
}
