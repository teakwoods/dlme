<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * In-memory implementation of ConsultantPresenceRepository for testing.
 */
final class InMemoryConsultantPresenceRepository implements ConsultantPresenceRepository
{
    /**
     * @var array<int, ConsultantPresence>
     */
    private array $presence = [];

    public function updatePresence(int $consultantId, ConsultantPresence $presence): void
    {
        $this->presence[$consultantId] = $presence;
    }

    public function getPresence(int $consultantId): ?ConsultantPresence
    {
        return $this->presence[$consultantId] ?? null;
    }

    public function clearPresence(int $consultantId): void
    {
        unset($this->presence[$consultantId]);
    }

    /**
     * Clear all stored presence (for testing).
     */
    public function clearAll(): void
    {
        $this->presence = [];
    }
}
