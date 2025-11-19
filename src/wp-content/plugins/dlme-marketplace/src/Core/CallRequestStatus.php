<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Call request lifecycle status.
 *
 * Tracks the lifecycle of a call request from creation through execution to completion.
 *
 * State transitions:
 * - PENDING → SCHEDULED (when sent to call system)
 * - PENDING → SUPERSEDED (when consultant reschedules)
 * - SCHEDULED → IN_PROGRESS (call started)
 * - SCHEDULED → EXPIRED (initiation window passed)
 * - IN_PROGRESS → COMPLETED (call ended successfully)
 * - IN_PROGRESS → FAILED (call failed)
 */
enum CallRequestStatus: string
{
    case PENDING = 'pending';           // Created, not yet sent to call system
    case SCHEDULED = 'scheduled';       // Sent to call system, waiting for execution time
    case IN_PROGRESS = 'in_progress';   // Call is happening now
    case COMPLETED = 'completed';       // Call finished successfully
    case SUPERSEDED = 'superseded';     // Replaced by a rescheduled request
    case EXPIRED = 'expired';           // Initiation window passed, never executed
    case FAILED = 'failed';             // Call attempt failed (no answer, error, etc)
}
