<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for managing checkout sessions and payment workflows.
 */
final class CheckoutService
{
    public function __construct(
        private readonly CheckoutSessionRepository $sessionRepository,
        private readonly ?PostPaymentWorkflow $postPaymentWorkflow = null,
        private readonly LoggerInterface $logger = new NullLogger()
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
            $this->logger->debug('dlme.checkout.session_reused', $this->buildContext([
                'idempotency_key' => $idempotencyKey,
                'session_id'      => $existing->id,
                'seller_id'       => $existing->sellerId,
                'buyer_id'        => $existing->buyerId,
                'product_id'      => $existing->productId,
                'status'          => $existing->status->value,
            ]));

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
            status: CheckoutSessionStatus::PENDING,
            buyerPhone: $click->buyerPhone,
            buyerEmail: $click->buyerEmail
        );

        $this->sessionRepository->save($session);

        $this->logger->info('dlme.checkout.session_started', $this->buildContext([
            'idempotency_key'     => $idempotencyKey,
            'session_id'          => $session->id,
            'seller_id'           => $session->sellerId,
            'buyer_id'            => $session->buyerId,
            'product_id'          => $session->productId,
            'sku'                 => $session->sku,
            'workflow_type'       => $session->workflowContext->workflowType,
            'cta_type'            => $session->ctaType->value,
            'page_type'           => $session->pageType->value,
            'availability_status' => $session->availabilityStatus->value,
            'referrer_url'        => $session->referrerUrl,
            'correlation_id'      => $session->correlationId,
        ]));

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

        $payload = new PaymentHandoffPayload(
            checkoutSessionId: $session->id,
            idempotencyKey: $session->idempotencyKey,
            sellerId: $session->sellerId,
            buyerId: $session->buyerId,
            productId: $session->productId,
            sku: $session->sku,
            quotedPrice: $session->quotedPrice,
            metadata: $metadata
        );

        $this->logger->info('dlme.payment.handoff_built', $this->buildContext([
            'session_id'      => $session->id,
            'idempotency_key' => $session->idempotencyKey,
            'seller_id'       => $session->sellerId,
            'buyer_id'        => $session->buyerId,
            'product_id'      => $session->productId,
            'sku'             => $session->sku,
            'amount'          => $session->quotedPrice,
            'workflow_type'   => $session->workflowContext->workflowType,
        ]));

        return $payload;
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
        $this->logger->info('dlme.payment.callback_received', $this->buildContext([
            'idempotency_key'      => $idempotencyKey,
            'provider'             => $payment->provider,
            'external_transaction' => $payment->externalTransactionId,
            'amount'               => $payment->amount,
            'currency'             => $payment->currency,
        ]));

        $session = $this->sessionRepository->markSucceededWithPayment($idempotencyKey, $payment);

        if ($session === null) {
            $this->logger->error('dlme.payment.session_not_found', [
                'idempotency_key' => $idempotencyKey,
            ]);

            return null;
        }

        // Check if status is as expected
        if ($session->status !== CheckoutSessionStatus::SUCCEEDED) {
            $this->logger->warning('dlme.payment.unexpected_status_after_success', [
                'idempotency_key' => $idempotencyKey,
                'session_id'      => $session->id,
                'status'          => $session->status->value,
            ]);

            return $session;
        }

        $this->logger->info('dlme.payment.session_succeeded', $this->buildContext([
            'session_id'           => $session->id,
            'idempotency_key'      => $session->idempotencyKey,
            'seller_id'            => $session->sellerId,
            'buyer_id'             => $session->buyerId,
            'product_id'           => $session->productId,
            'sku'                  => $session->sku,
            'external_transaction' => $payment->externalTransactionId,
            'workflow_type'        => $session->workflowContext->workflowType,
            'provider'             => $payment->provider,
            'amount'               => $payment->amount,
            'currency'             => $payment->currency,
        ]));

        // Call workflow if session exists and workflow is configured
        if ($this->postPaymentWorkflow !== null) {
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
        $session = $this->sessionRepository->markFailed($idempotencyKey, $reason);

        if ($session === null) {
            $this->logger->error('dlme.payment.session_not_found', [
                'idempotency_key' => $idempotencyKey,
                'reason'          => $reason,
            ]);

            return null;
        }

        $this->logger->info('dlme.payment.failed', $this->buildContext([
            'session_id'      => $session->id,
            'idempotency_key' => $session->idempotencyKey,
            'seller_id'       => $session->sellerId,
            'buyer_id'        => $session->buyerId,
            'product_id'      => $session->productId,
            'reason'          => $reason,
            'workflow_type'   => $session->workflowContext->workflowType,
        ]));

        return $session;
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

    /**
     * Builds logging context, filtering out null and empty string values.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildContext(array $context): array
    {
        return array_filter($context, fn($value) => $value !== null && $value !== '');
    }
}
