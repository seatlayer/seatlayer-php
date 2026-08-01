<?php

declare(strict_types=1);

namespace SeatLayer;

/**
 * 409 — the seats moved under you. Normal in ticketing, not exceptional: two
 * buyers wanted the same seat and one lost.
 */
class ConflictException extends SeatLayerException
{
    /** @return list<array<string, mixed>> Per-object conflicts, when reported. */
    public function conflicts(): array
    {
        $conflicts = $this->body['conflicts'] ?? null;

        return is_array($conflicts) ? array_values(array_filter($conflicts, 'is_array')) : [];
    }

    /** Best-available could not find enough free inventory. */
    public function isSoldOut(): bool
    {
        return in_array($this->body['reason'] ?? null, ['sold_out', 'not_enough_together'], true);
    }
}
