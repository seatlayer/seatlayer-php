<?php

declare(strict_types=1);

namespace SeatLayer;

/** 429. `retryAfterSeconds` prefers the header over the JSON field. */
class RateLimitException extends SeatLayerException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        int $status,
        string $code,
        array $body,
        ?string $requestId,
        string $message,
        public readonly float $retryAfterSeconds,
    ) {
        parent::__construct($status, $code, $body, $requestId, $message);
    }
}
