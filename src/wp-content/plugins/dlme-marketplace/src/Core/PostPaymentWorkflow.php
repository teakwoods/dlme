<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Interface for post-payment workflow hooks.
 *
 * Core never implements this; it's plugged in by the integration layer.
 */
interface PostPaymentWorkflow
{
    /**
     * Called when a payment is confirmed for a checkout session.
     *
     * This is called exactly once per successful payment.
     */
    public function onPaymentConfirmed(
        CheckoutSession $session,
        PaymentDetails $payment
    ): void;
}
