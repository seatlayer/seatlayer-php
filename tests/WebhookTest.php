<?php

declare(strict_types=1);

namespace SeatLayer\Tests;

use PHPUnit\Framework\TestCase;
use SeatLayer\Webhook;
use SeatLayer\WebhookVerificationException;

/** Webhook verification — the piece integrations most often get wrong. */
final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private function sign(string $payload, string $secret = self::SECRET): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public function testAcceptsCorrectlySignedDelivery(): void
    {
        $payload = (string) json_encode(['type' => 'booking.created', 'occurrenceId' => 'occ_1']);
        $event = Webhook::verify($payload, $this->sign($payload), self::SECRET);
        self::assertSame('booking.created', $event['type']);
    }

    public function testRejectsReserialisedBody(): void
    {
        // The classic integration bug: re-encoding the decoded body reorders keys
        // and the bytes no longer match what was signed.
        $original = '{"a":1,"b":2}';
        $reserialised = json_encode(json_decode('{"b":2,"a":1}', true));

        $this->expectException(WebhookVerificationException::class);
        Webhook::verify((string) $reserialised, $this->sign($original), self::SECRET);
    }

    public function testRejectsWrongSecret(): void
    {
        $payload = '{"ok":true}';
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/did not match/');
        Webhook::verify($payload, $this->sign($payload, 'whsec_other'), self::SECRET);
    }

    public function testRejectsMissingHeader(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/Missing X-SeatLayer-Signature/');
        Webhook::verify('{}', null, self::SECRET);
    }

    public function testRejectsUnknownScheme(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/Unsupported signature format/');
        Webhook::verify('{}', 'md5=abc', self::SECRET);
    }

    public function testRejectsTruncatedSignature(): void
    {
        $payload = '{"ok":true}';
        $this->expectException(WebhookVerificationException::class);
        Webhook::verify($payload, substr($this->sign($payload), 0, 20), self::SECRET);
    }

    public function testRequiresSecret(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/signing secret is required/');
        Webhook::verify('{}', $this->sign('{}'), '');
    }

    public function testReportsVerifiedButUnparseableBodyDistinctly(): void
    {
        $payload = 'not json';
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/not valid JSON/');
        Webhook::verify($payload, $this->sign($payload), self::SECRET);
    }
}
