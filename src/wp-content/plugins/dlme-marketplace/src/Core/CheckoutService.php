<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;

/**
 * Service for managing checkout sessions and payment workflows.
 */
final class CheckoutService
{
    public function __construct(
        private readonly CheckoutSessionRepository $sessionRepository,
        private readonly ?PostPaymentWorkflow $postPaymentWorkflow = null
    ) {
    }

    /**
     * Starts a checkout session from a button click.
     *
     * Idempotent: returns existing session if idempotency key already exists.
     */
    public function startFromClick(
        ButtonClickContext $click,
        string $idempotencyKey,
        WorkflowContext $workflowContext
    ): CheckoutSession {
        // Check for existing session (idempotency)
        $existing = $this->sessionRepository->findByIdempotencyKey($idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        // Create new session
        $session = new CheckoutSession(
            id: $this->generateSessionId(),
            idempotencyKey: $idempotencyKey,
            sellerId: $click->sellerId,
            buyerId: $click->buyerId,
            productId: $click->productId,
            sku: $click->sku,
            quotedPrice: $click->price,
            pageType: $click->pageType,
            ctaType: $click->ctaType,
            availabilityStatus: $click->availabilityStatus,
            referrerUrl: $click->referrerUrl,
            correlationId: $click->correlationId,
            workflowContext: $workflowContext,
            createdAt: $click->clickedAt,
            status: CheckoutSessionStatus::PENDING
        );

        $this->sessionRepository->save($session);
        return $session;
    }

    /**
     * Builds payment handoff payload with metadata.
     */
    public function buildPaymentHandoffPayload(
        CheckoutSession $session
    ): PaymentHandoffPayload {
        $metadata = [
            'dlme_session_id' => $session->id,
            'dlme_idempotency_key' => $session->idempotencyKey,
            'dlme_seller_id' => (string)$session->sellerId,
            'dlme_cta_type' => $session->ctaType->value,
            'dlme_page_type' => $session->pageType->value,
            'dlme_availability_status' => $session->availabilityStatus->value,
            'dlme_workflow_type' => $session->workflowContext->workflowType,
        ];

        // Add optional fields
        if ($session->productId !== null) {
            $metadata['dlme_product_id'] = (string)$session->productId;
        }

        if ($session->buyerId !== null) {
            $metadata['dlme_buyer_id'] = (string)$session->buyerId;
        }

        if ($session->sku !== null) {
            $metadata['dlme_sku'] = $session->sku;
        }

        if ($session->referrerUrl !== null) {
            $metadata['dlme_referrer_url'] = $session->referrerUrl;
        }

        if ($session->correlationId !== null) {
            $metadata['dlme_correlation_id'] = $session->correlationId;
        }

        // Add workflow context attributes
        foreach ($session->workflowContext->attributes as $key => $value) {
            $metadata["dlme_workflow_{$key}"] = $value;
        }

        return new PaymentHandoffPayload(
            checkoutSessionId: $session->id,
            idempotencyKey: $session->idempotencyKey,
            sellerId: $session->sellerId,
            buyerId: $session->buyerId,
            productId: $session->productId,
            sku: $session->sku,
            quotedPrice: $session->quotedPrice,
            metadata: $metadata
        );
    }

    /**
     * Marks payment as successful.
     *
     * Calls PostPaymentWorkflow::onPaymentConfirmed() on every successful call,
     * but repository ensures idempotency (only transitions PENDING->SUCCEEDED once).
     */
    public function markPaymentSuccessful(
        string $idempotencyKey,
        PaymentDetails $payment
    ): ?CheckoutSession {
        $session = $this->sessionRepository->markSucceededWithPayment($idempotencyKey, $payment);

        // Call workflow if session exists and workflow is configured
        if ($session !== null && $this->postPaymentWorkflow !== null) {
            $this->postPaymentWorkflow->onPaymentConfirmed($session, $payment);
        }

        return $session;
    }

    /**
     * Marks payment as failed.
     */
    public function markPaymentFailed(
        string $idempotencyKey,
        string $reason
    ): ?CheckoutSession {
        return $this->sessionRepository->markFailed($idempotencyKey, $reason);
    }

    /**
     * Checks if payment amount matches quoted price within tolerance.
     */
    public function paymentMatchesQuote(
        CheckoutSession $session,
        PaymentDetails $payment,
        float $tolerance = 0.01
    ): bool {
        if ($session->quotedPrice === null || $payment->amount === null) {
            return false;
        }

        $diff = abs($session->quotedPrice - $payment->amount);
        return $diff <= $tolerance;
    }

    /**
     * Generates a unique session ID.
     */
    private function generateSessionId(): string
    {
        // Generate UUIDv4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return 'sess_' . $uuid;
    }
}
