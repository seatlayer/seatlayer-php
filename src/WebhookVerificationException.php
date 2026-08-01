<?php

declare(strict_types=1);

namespace SeatLayer;

/** The delivery did not come from SeatLayer. Respond 400; do not process it. */
final class WebhookVerificationException extends \RuntimeException
{
}
