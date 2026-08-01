<?php

declare(strict_types=1);

namespace SeatLayer;

/**
 * Webhook signature verification.
 *
 * The most security-sensitive thing an integrator writes by hand, and the two
 * classic mistakes are both easy to make and silent:
 *
 *   1. verifying against a re-serialised body (json_encode of the decoded array),
 *      which changes bytes and fails — or worse, gets "fixed" by skipping
 *      verification entirely;
 *   2. comparing signatures with ==, which leaks the expected value through timing.
 *
 * So the SDK does it, takes the RAW body, and compares in constant time.
 */
final class Webhook
{
    /**
     * Verify a delivery and return its decoded payload.
     *
     * $payload must be the raw request body — in most frameworks that is
     * file_get_contents('php://input'), $request->getContent() in Symfony, or
     * $request->getContent() in Laravel. Never a decoded array re-encoded.
     *
     * NOTE ON REPLAY: deliveries are signed over the body, which carries an `at`
     * timestamp — but nothing enforces a freshness window, so a captured delivery
     * stays valid indefinitely. Replay protection is yours: every event carries an
     * `occurrenceId`, and the correct pattern is to record processed ids and
     * ignore repeats. Do not skip this.
     *
     * @return array<string, mixed>
     * @throws WebhookVerificationException when the delivery is not from SeatLayer
     */
    public static function verify(string $payload, ?string $signature, string $secret): array
    {
        if ($secret === '') {
            throw new WebhookVerificationException('A webhook signing secret is required.');
        }
        if ($signature === null || $signature === '') {
            throw new WebhookVerificationException('Missing X-SeatLayer-Signature header.');
        }

        $parts = explode('=', $signature, 2);
        if (count($parts) !== 2 || $parts[0] !== 'sha256' || $parts[1] === '') {
            throw new WebhookVerificationException(sprintf(
                'Unsupported signature format "%s"; expected "sha256=<hex>".',
                $signature,
            ));
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        // hash_equals is constant time and handles a length mismatch without
        // leaking which of the two failures occurred.
        if (!hash_equals($expected, $parts[1])) {
            throw new WebhookVerificationException('Webhook signature did not match.');
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new WebhookVerificationException(
                'Signature verified but the body is not valid JSON: ' . $error->getMessage(),
            );
        }

        if (!is_array($decoded)) {
            throw new WebhookVerificationException('Signature verified but the body is not a JSON object.');
        }

        /** @var array<string, mixed> */
        return $decoded;
    }
}
