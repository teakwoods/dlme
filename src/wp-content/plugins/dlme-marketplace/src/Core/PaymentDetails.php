<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeInterface;

/**
 * Details about a payment transaction.
 */
final class PaymentDetails
{
    public function __construct(
        public readonly string $provider,
        public readonly string $externalTransactionId,
        public readonly ?string $paymentInstrumentRef,
        public readonly ?string $currency,
        public readonly ?float $amount,
        public readonly ?DateTimeInterface $paidAt
    ) {
    }
}
