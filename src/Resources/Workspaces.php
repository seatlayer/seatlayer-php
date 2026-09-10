<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;
use SeatLayer\EventHostingRegion;

/** Workspaces isolate one tenant's charts and events from another's. */
final class Workspaces
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/workspaces');
    }

    /** @return array<string, mixed> */
    public function create(string $name, ?string $externalRef = null, ?string $idempotencyKey = null, ?string $defaultRegion = null): array
    {
        EventHostingRegion::assert($defaultRegion);
        $body = array_filter(
            ['name' => $name, 'externalRef' => $externalRef, 'defaultRegion' => $defaultRegion],
            static fn (mixed $v): bool => $v !== null,
        );

        /** @var array<string, mixed> */
        return (array) $this->http->postWithHeaderReplay('/v1/workspaces', $body, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $workspaceId): array
    {
        /** @var array<string, mixed> */
        return (array) $this->http->get('/v1/workspaces/' . HttpClient::encode($workspaceId));
    }

    /**
     * Rename, re-reference, or disable a workspace.
     *
     * The organisation's default workspace cannot be disabled — the API answers
     * 409 `default_workspace_required`. Promote another one first.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function update(string $workspaceId, array $fields): array
    {
        $defaultRegion = $fields['defaultRegion'] ?? null;
        if ($defaultRegion !== null && !is_string($defaultRegion)) {
            throw new \InvalidArgumentException('defaultRegion must be a supported SeatLayer Event region.');
        }
        EventHostingRegion::assert($defaultRegion);
        /** @var array<string, mixed> */
        return (array) $this->http->patch('/v1/workspaces/' . HttpClient::encode($workspaceId), $fields);
    }
}
