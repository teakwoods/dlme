<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Repository interface for storing and retrieving consultant presence.
 *
 * Presence is real-time availability state received from external call system.
 * Only stores the LATEST presence for each consultant (upsert pattern).
 *
 * Production implementation (by full-scope agent):
 * - Custom database table: wp_dlme_consultant_presence
 * - One row per consultant (upsert on webhook)
 * - Indexed on consultant_id for fast lookups
 *
 * Test implementation (SANDBOX):
 * - InMemoryConsultantPresenceRepository with array storage
 */
interface ConsultantPresenceRepository
{
    /**
     * Update presence for a consultant.
     *
     * Upserts (insert or update) the latest presence.
     * Replaces any existing presence for the consultant.
     *
     * @param int $consultantId Consultant ID
     * @param ConsultantPresence $presence Latest presence state
     */
    public function updatePresence(int $consultantId, ConsultantPresence $presence): void;

    /**
     * Get current presence for a consultant.
     *
     * Returns most recent presence received via webhook, or null if never seen.
     *
     * @param int $consultantId Consultant ID
     * @return ConsultantPresence|null Current presence, or null if not found
     */
    public function getPresence(int $consultantId): ?ConsultantPresence;

    /**
     * Clear presence for a consultant.
     *
     * Useful for testing or manual reset scenarios.
     *
     * @param int $consultantId Consultant ID
     */
    public function clearPresence(int $consultantId): void;
}
