<?php

declare(strict_types=1);

namespace SeatLayer\Resources;

use SeatLayer\HttpClient;

/**
 * Published SeatLayer catalogue templates.
 *
 * Instantiation creates an independent draft chart. Publish that returned chart
 * before creating an event from it.
 */
final class Templates
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Instantiate a public template into a new draft chart.
     *
     * The API requires a JSON object even when no overrides are needed. This
     * resource therefore serializes the default as `{}`, rather than PHP's `[]`.
     *
     * @param array<string, mixed> $fields Optional template overrides.
     * @return array<string, mixed>
     */
    public function instantiateTemplate(
        string $templateId,
        array $fields = [],
        ?string $idempotencyKey = null,
    ): array {
        /** @var array<string, mixed> */
        return (array) $this->http->postObjectWithHeaderReplay(
            '/v1/templates/' . HttpClient::encode($templateId) . '/instantiate',
            $fields,
            $idempotencyKey,
        );
    }
}
