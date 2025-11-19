<?php

declare(strict_types=1);

namespace DLme\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for managing consultant presence from external call system.
 *
 * Responsibilities:
 * - Receive presence webhooks from external system
 * - Update presence repository
 * - Query current presence for availability checks
 * - Determine if consultant can take calls
 *
 * Presence states:
 * - 'idle': Available and not in call
 * - 'in_call': Currently on an active call
 * - 'offline': Not available
 */
final class ConsultantPresenceService
{
    public function __construct(
        private readonly ConsultantPresenceRepository $repository,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Update presence from webhook payload.
     *
     * Called when external call system sends presence update.
     * Upserts presence in repository and logs the update.
     *
     * @param int $consultantId Consultant ID
     * @param ConsultantPresence $presence New presence state
     */
    public function updateFromWebhook(int $consultantId, ConsultantPresence $presence): void
    {
        $this->repository->updatePresence($consultantId, $presence);

        $this->logger->info('dlme.presence.updated', [
            'consultant_id' => $consultantId,
            'status' => $presence->status,
            'timestamp' => $presence->timestamp->format('c'),
            'session_id' => $presence->sessionId,
            'current_call_request_id' => $presence->currentCallRequestId,
        ]);
    }

    /**
     * Get current presence for a consultant.
     *
     * Returns most recent presence received via webhook, or null if never seen.
     *
     * @param int $consultantId Consultant ID
     * @return ConsultantPresence|null Current presence, or null if not found
     */
    public function getCurrentPresence(int $consultantId): ?ConsultantPresence
    {
        return $this->repository->getPresence($consultantId);
    }

    /**
     * Check if consultant is available to take a call.
     *
     * Returns true only if presence status is 'idle'.
     * Returns false for 'in_call', 'offline', or no presence.
     *
     * @param int $consultantId Consultant ID
     * @return bool True if available, false otherwise
     */
    public function isAvailableForCall(int $consultantId): bool
    {
        $presence = $this->repository->getPresence($consultantId);

        if ($presence === null) {
            return false;
        }

        return $presence->status === 'idle';
    }
}
