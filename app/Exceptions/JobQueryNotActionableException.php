<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a contractor tries to accept or decline a job Query that can no
 * longer be acted on. Carries a machine-readable reason so each caller (mobile
 * JSON API vs. web UI) can translate it into the right response.
 */
class JobQueryNotActionableException extends RuntimeException
{
    public const REASON_TAKEN = 'taken';

    public const REASON_RESPONDED = 'responded';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /**
     * Another contractor accepted the job first — this copy is locked out.
     */
    public static function taken(): self
    {
        return new self(self::REASON_TAKEN, 'This job has already been accepted by another contractor.');
    }

    /**
     * This contractor has already responded to (accepted/declined/expired) the job.
     */
    public static function responded(): self
    {
        return new self(self::REASON_RESPONDED, 'This job has already been responded to.');
    }
}
