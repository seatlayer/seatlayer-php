<?php

declare(strict_types=1);

namespace SeatLayer;

/** 401/403 — bad key, revoked key, or a live key used against a test event. */
class AuthException extends SeatLayerException
{
    /**
     * The key's mode and the event's mode disagree — the most common cause of a
     * "works locally, 403s in production" report.
     */
    public function isModeMismatch(): bool
    {
        return $this->errorCode === 'mode_mismatch';
    }
}
