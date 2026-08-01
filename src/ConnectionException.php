<?php

declare(strict_types=1);

namespace SeatLayer;

/** The request never got an answer: DNS, TLS, socket, or timeout. */
class ConnectionException extends \RuntimeException
{
}
