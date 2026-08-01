<?php

declare(strict_types=1);

namespace SeatLayer;

/**
 * Typed errors.
 *
 * The API answers failures with `{"error": ..., "code": ..., "message": ...}` and a
 * status. Surfacing that as one opaque exception leaves every caller string-matching
 * on `error`. The classes below are the ones an integration actually branches on — a
 * sold-out seat is a business outcome that belongs in an `if`, not in a `catch` that
 * also swallows a bad key.
 */
class SeatLayerException extends \RuntimeException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public readonly int $status,
        /**
         * Machine-readable code: `body.code ?? body.error`.
         *
         * Named `errorCode`, not `code`, because PHP's base Exception already
         * owns `$code` as an int and a property cannot be redeclared readonly.
         * Other SeatLayer SDKs expose the same value as `code`.
         */
        public readonly string $errorCode,
        public readonly array $body,
        /** Correlation id from `X-Request-ID`. Quote it in support requests. */
        public readonly ?string $requestId,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(
        int $status,
        array $body,
        ?string $requestId,
        float $retryAfterSeconds,
    ): self {
        $code = self::stringOrNull($body['code'] ?? null)
            ?? self::stringOrNull($body['error'] ?? null)
            ?? 'unknown_error';
        $message = self::stringOrNull($body['message'] ?? null)
            ?? sprintf('SeatLayer API error %d (%s)', $status, $code);

        return match (true) {
            $status === 401, $status === 403 => new AuthException($status, $code, $body, $requestId, $message),
            $status === 404 => new NotFoundException($status, $code, $body, $requestId, $message),
            $status === 409 => new ConflictException($status, $code, $body, $requestId, $message),
            $status === 422 => new ValidationException($status, $code, $body, $requestId, $message),
            $status === 429 => new RateLimitException($status, $code, $body, $requestId, $message, $retryAfterSeconds),
            default => new self($status, $code, $body, $requestId, $message),
        };
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
