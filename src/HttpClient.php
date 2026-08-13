<?php

declare(strict_types=1);

namespace SeatLayer;

/**
 * The transport: auth, idempotency, retry, and error mapping.
 *
 * Built on ext-curl rather than Guzzle. A server SDK that drags in a dependency
 * tree becomes a supply-chain surface for every customer who installs it, and
 * this client needs nothing curl cannot do. Callers who want their own PSR-18
 * client can pass a `$transport` callable.
 */
final class HttpClient
{
    public const DEFAULT_BASE_URL = 'https://api.seatlayer.io';
    public const DEFAULT_MAX_RETRIES = 3;
    public const DEFAULT_TIMEOUT = 30.0;

    /** The API's own charset for Idempotency-Key. */
    private const IDEMPOTENCY_KEY_PATTERN = '/^[A-Za-z0-9._:-]{1,128}$/';

    public readonly string $baseUrl;

    /** `live`, `test`, or `unknown`, derived from the key prefix. */
    public readonly string $mode;

    /** @var null|callable(string, string, array<string, string>, ?string, float): array{status:int, body:string, headers:array<string,string>} */
    private $transport;

    /**
     * @param null|callable(string, string, array<string, string>, ?string, float): array{status:int, body:string, headers:array<string,string>} $transport
     */
    public function __construct(
        private readonly string $secretKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        ?callable $transport = null,
    ) {
        if ($secretKey === '') {
            throw new \InvalidArgumentException('A SeatLayer secret key is required.');
        }
        // Caught here rather than as a 401 three round-trips later. The pk_ case
        // gets its own message: it is the one people paste by mistake.
        if (str_starts_with($secretKey, 'pk_')) {
            throw new \InvalidArgumentException(
                'That is a publishable key. The server SDK needs a secret key (sk_live_… or sk_test_…).'
            );
        }
        if (!str_starts_with($secretKey, 'sk_')) {
            throw new \InvalidArgumentException('A SeatLayer secret key starts with sk_live_ or sk_test_.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport;
        $this->mode = match (true) {
            str_starts_with($secretKey, 'sk_test_') => 'test',
            str_starts_with($secretKey, 'sk_live_') => 'live',
            default => 'unknown',
        };
    }

    public static function assertValidIdempotencyKey(string $key): void
    {
        if (preg_match(self::IDEMPOTENCY_KEY_PATTERN, $key) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid Idempotency-Key "%s": allowed characters are A-Z a-z 0-9 . _ : - and the length must be 1-128.',
                $key,
            ));
        }
    }

    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $body
     */
    public function request(
        string $method,
        string $path,
        ?array $query = null,
        ?array $body = null,
        ?string $idempotencyKey = null,
    ): mixed {
        return $this->performRequest($method, $path, $query, $body, false, $idempotencyKey);
    }

    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|object|string|null $body
     */
    private function performRequest(
        string $method,
        string $path,
        ?array $query,
        array|object|string|null $body,
        bool $headerReplay,
        ?string $idempotencyKey,
        ?string $contentType = null,
    ): mixed {
        $url = $this->baseUrl . $path;
        if ($query !== null) {
            $filtered = array_filter($query, static fn (mixed $v): bool => $v !== null);
            if ($filtered !== []) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
            'User-Agent' => 'seatlayer-php',
        ];

        $payload = null;
        if ($body !== null) {
            $payload = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);
            $headers['Content-Type'] = $contentType ?? 'application/json';
        }

        $read = in_array(strtoupper($method), ['GET', 'HEAD'], true);
        if (!$read && ($headerReplay || $idempotencyKey !== null)) {
            $key = $idempotencyKey ?? self::uuidV4();
            self::assertValidIdempotencyKey($key);
            $headers['Idempotency-Key'] = $key;
        }

        $attemptLimit = $read || $headerReplay ? $this->maxRetries : 1;
        $lastError = null;
        for ($attempt = 0; $attempt < $attemptLimit; $attempt++) {
            try {
                $response = $this->send($method, $url, $headers, $payload);
            } catch (ConnectionException $error) {
                $lastError = $error;
                if ($attempt < $attemptLimit - 1) {
                    self::sleepSeconds(self::backoffSeconds($attempt, null));
                    continue;
                }
                throw $error;
            }

            $status = $response['status'];
            if ($status >= 200 && $status < 300) {
                if ($status === 204 || $response['body'] === '') {
                    return null;
                }

                return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            }

            $decoded = json_decode($response['body'], true);
            /** @var array<string, mixed> $errorBody */
            $errorBody = is_array($decoded) ? $decoded : [];
            $retryAfter = self::parseRetryAfter($response['headers'], $errorBody);

            if (self::isRetryableStatus($status) && $attempt < $attemptLimit - 1) {
                self::sleepSeconds(self::backoffSeconds($attempt, $status === 429 ? $retryAfter : null));
                continue;
            }

            throw SeatLayerException::fromResponse(
                $status,
                $errorBody,
                $response['headers']['x-request-id'] ?? null,
                $retryAfter,
            );
        }

        throw $lastError ?? new ConnectionException('Request failed with no attempts made.');
    }

    /** @param array<string, mixed>|null $query */
    public function get(string $path, ?array $query = null): mixed
    {
        return $this->request('GET', $path, $query);
    }

    /** @param array<string, mixed>|null $body */
    public function post(string $path, ?array $body = null, ?string $idempotencyKey = null): mixed
    {
        return $this->performRequest('POST', $path, null, $body, false, $idempotencyKey);
    }

    /** @param array<string, mixed>|null $body */
    public function postWithHeaderReplay(
        string $path,
        ?array $body = null,
        ?string $idempotencyKey = null,
    ): mixed {
        return $this->performRequest('POST', $path, null, $body, true, $idempotencyKey);
    }

    /**
     * POST a JSON object with exact response replay, including `{}` when empty.
     *
     * @param array<string, mixed> $body
     */
    public function postObjectWithHeaderReplay(
        string $path,
        array $body = [],
        ?string $idempotencyKey = null,
    ): mixed {
        return $this->performRequest('POST', $path, null, (object) $body, true, $idempotencyKey);
    }

    /** @param array<string, mixed> $body */
    public function put(string $path, array $body): mixed
    {
        return $this->performRequest('PUT', $path, null, $body, false, null);
    }

    /** Upload raw poster bytes without JSON/base64 transformation. */
    public function putBinary(string $path, string $bytes, string $contentType): mixed
    {
        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'application/octet-stream'];
        if (!in_array($contentType, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported poster content type: ' . $contentType);
        }

        return $this->performRequest('PUT', $path, null, $bytes, false, null, $contentType);
    }

    /** @param array<string, mixed> $body */
    public function patch(string $path, array $body): mixed
    {
        return $this->performRequest('PATCH', $path, null, $body, false, null);
    }

    /** @param array<string, mixed>|null $query */
    public function delete(string $path, ?array $query = null): mixed
    {
        return $this->performRequest('DELETE', $path, $query, null, false, null);
    }

    /** Percent-encode a path segment, including slashes. */
    public static function encode(string $segment): string
    {
        return rawurlencode($segment);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:string, headers:array<string,string>}
     */
    private function send(string $method, string $url, array $headers, ?string $payload): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($method, $url, $headers, $payload, $this->timeout);
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new ConnectionException('Could not initialise a curl handle.');
        }

        $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ceil($this->timeout),
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $name, string $value): string => $name . ': ' . $value,
                array_keys($headers),
                array_values($headers),
            ),
            CURLOPT_HEADERFUNCTION => static function ($_handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($handle);
        if ($body === false) {
            $message = curl_error($handle);
            curl_close($handle);

            throw new ConnectionException(sprintf('Request to %s %s failed: %s', $method, $url, $message));
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'headers' => $responseHeaders];
    }

    /**
     * Retry only what is safe to retry. 429 and 5xx are transient by definition;
     * a 4xx is the API saying the request itself is wrong, and retrying it only
     * burns rate-limit budget and delays the error the caller needs to see.
     */
    private static function isRetryableStatus(int $status): bool
    {
        return $status === 429 || $status === 408 || ($status >= 500 && $status < 600);
    }

    private static function backoffSeconds(int $attempt, ?float $retryAfter): float
    {
        // The server's instruction wins — it knows when the window rolls over.
        if ($retryAfter !== null) {
            return $retryAfter;
        }
        // Otherwise exponential with full jitter, so a fleet of workers limited at
        // the same moment does not retry in lockstep and re-limit itself.
        $ceiling = min(8.0, 0.25 * (2 ** $attempt));

        return (mt_rand() / mt_getrandmax()) * $ceiling;
    }

    private static function sleepSeconds(float $seconds): void
    {
        usleep((int) round($seconds * 1_000_000));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     */
    private static function parseRetryAfter(array $headers, array $body): float
    {
        $header = $headers['retry-after'] ?? null;
        if ($header !== null && is_numeric($header) && (float) $header >= 0) {
            return (float) $header;
        }
        // Fall back to the JSON field for routes that predate the headers.
        $field = $body['retryAfterSeconds'] ?? null;

        return is_int($field) || is_float($field) ? (float) $field : 1.0;
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
