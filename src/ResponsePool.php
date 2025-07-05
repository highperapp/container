<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container;

/**
 * High-Performance Response Object Pool
 *
 * Object pooling for Request/Response objects
 * to reduce memory allocation overhead and improve performance.
 */
class ResponsePool
{
    private array $responsePool = [];
    private array $requestPool = [];
    private array $jsonCache = [];
    private array $headerCache = [];

    private int $maxPoolSize = 1000;
    private int $maxCacheSize = 5000;

    // Statistics
    private int $responseHits = 0;
    private int $responseMisses = 0;
    private int $requestHits = 0;
    private int $requestMisses = 0;
    private int $jsonCacheHits = 0;
    private int $jsonCacheMisses = 0;

    /**
     * Get a response object from the pool
     */
    public function getResponse(): object
    {
        if (!empty($this->responsePool)) {
            $this->responseHits++;
            return array_pop($this->responsePool);
        }

        $this->responseMisses++;
        return $this->createFreshResponse();
    }

    /**
     * Return a response object to the pool
     */
    public function returnResponse(object $response): void
    {
        if (count($this->responsePool) < $this->maxPoolSize) {
            $this->resetResponse($response);
            $this->responsePool[] = $response;
        }
    }

    /**
     * Get a request object from the pool
     */
    public function getRequest(): object
    {
        if (!empty($this->requestPool)) {
            $this->requestHits++;
            return array_pop($this->requestPool);
        }

        $this->requestMisses++;
        return $this->createFreshRequest();
    }

    /**
     * Return a request object to the pool
     */
    public function returnRequest(object $request): void
    {
        if (count($this->requestPool) < $this->maxPoolSize) {
            $this->resetRequest($request);
            $this->requestPool[] = $request;
        }
    }

    /**
     * Generate optimized JSON response with caching
     */
    public function jsonResponse(array $data, int $status = 200, array $headers = []): array
    {
        $dataHash = $this->hashData($data);
        $headerHash = $this->hashData($headers);
        $cacheKey = $dataHash . ':' . $headerHash . ':' . $status;

        if (isset($this->jsonCache[$cacheKey])) {
            $this->jsonCacheHits++;
            return $this->jsonCache[$cacheKey];
        }

        $this->jsonCacheMisses++;

        // Generate optimized response
        $response = [
            'status' => $status,
            'headers' => $this->optimizeHeaders($headers + [
                'Content-Type' => 'application/json',
                'X-Powered-By' => 'HighPer-Framework-Phase2'
            ]),
            'body' => $this->optimizeJsonEncoding($data)
        ];

        // Cache if under limit
        if (count($this->jsonCache) < $this->maxCacheSize) {
            $this->jsonCache[$cacheKey] = $response;
        }

        return $response;
    }

    /**
     * Generate optimized headers with caching
     */
    public function optimizeHeaders(array $headers): array
    {
        $headerKey = $this->hashData($headers);

        if (isset($this->headerCache[$headerKey])) {
            return $this->headerCache[$headerKey];
        }

        // Optimize header generation
        $optimizedHeaders = [];
        foreach ($headers as $name => $value) {
            // Normalize header names for better caching
            $normalizedName = $this->normalizeHeaderName($name);
            $optimizedHeaders[$normalizedName] = $this->optimizeHeaderValue($value);
        }

        // Cache if under limit
        if (count($this->headerCache) < $this->maxCacheSize) {
            $this->headerCache[$headerKey] = $optimizedHeaders;
        }

        return $optimizedHeaders;
    }

    /**
     * Optimized JSON encoding
     */
    private function optimizeJsonEncoding(array $data): string
    {
        // Use optimized JSON encoding flags for better performance
        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_PRESERVE_ZERO_FRACTION
        ) ?: '{}';
    }

    /**
     * Create a fresh response object
     */
    private function createFreshResponse(): object
    {
        return new class {
            public int $status = 200;
            public array $headers = [];
            public string $body = '';

            public function withStatus(int $status): self
            {
                $this->status = $status;
                return $this;
            }

            public function withHeader(string $name, string $value): self
            {
                $this->headers[$name] = $value;
                return $this;
            }

            public function withBody(string $body): self
            {
                $this->body = $body;
                return $this;
            }

            public function getStatus(): int
            {
                return $this->status;
            }
            public function getHeaders(): array
            {
                return $this->headers;
            }
            public function getBody(): string
            {
                return $this->body;
            }
        };
    }

    /**
     * Create a fresh request object
     */
    private function createFreshRequest(): object
    {
        return new class {
            public string $method = 'GET';
            public string $path = '/';
            public array $headers = [];
            public string $body = '';
            public array $params = [];

            public function withMethod(string $method): self
            {
                $this->method = $method;
                return $this;
            }

            public function withPath(string $path): self
            {
                $this->path = $path;
                return $this;
            }

            public function withHeader(string $name, string $value): self
            {
                $this->headers[$name] = $value;
                return $this;
            }

            public function withBody(string $body): self
            {
                $this->body = $body;
                return $this;
            }

            public function getMethod(): string
            {
                return $this->method;
            }
            public function getPath(): string
            {
                return $this->path;
            }
            public function getHeaders(): array
            {
                return $this->headers;
            }
            public function getBody(): string
            {
                return $this->body;
            }
        };
    }

    /**
     * Reset response object for reuse
     */
    private function resetResponse(object $response): void
    {
        if (property_exists($response, 'status')) {
            $response->status = 200;
        }
        if (property_exists($response, 'headers')) {
            $response->headers = [];
        }
        if (property_exists($response, 'body')) {
            $response->body = '';
        }
    }

    /**
     * Reset request object for reuse
     */
    private function resetRequest(object $request): void
    {
        if (property_exists($request, 'method')) {
            $request->method = 'GET';
        }
        if (property_exists($request, 'path')) {
            $request->path = '/';
        }
        if (property_exists($request, 'headers')) {
            $request->headers = [];
        }
        if (property_exists($request, 'body')) {
            $request->body = '';
        }
        if (property_exists($request, 'params')) {
            $request->params = [];
        }
    }

    /**
     * Generate fast hash for data caching
     */
    private function hashData(array $data): string
    {
        // Use xxHash-like algorithm for faster hashing
        return hash('xxh64', serialize($data));
    }

    /**
     * Normalize header name for caching
     */
    private function normalizeHeaderName(string $name): string
    {
        return ucwords(strtolower($name), '-');
    }

    /**
     * Optimize header value
     */
    private function optimizeHeaderValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    /**
     * Clear all pools and caches
     */
    public function clear(): void
    {
        $this->responsePool = [];
        $this->requestPool = [];
        $this->jsonCache = [];
        $this->headerCache = [];

        // Reset statistics
        $this->responseHits = 0;
        $this->responseMisses = 0;
        $this->requestHits = 0;
        $this->requestMisses = 0;
        $this->jsonCacheHits = 0;
        $this->jsonCacheMisses = 0;
    }

    /**
     * Get pool statistics
     */
    public function getStats(): array
    {
        $totalResponseRequests = $this->responseHits + $this->responseMisses;
        $totalRequestRequests = $this->requestHits + $this->requestMisses;
        $totalJsonRequests = $this->jsonCacheHits + $this->jsonCacheMisses;

        return [
            'response_pool' => [
                'size' => count($this->responsePool),
                'hits' => $this->responseHits,
                'misses' => $this->responseMisses,
                'hit_ratio' => $totalResponseRequests > 0 ?
                    round(($this->responseHits / $totalResponseRequests) * 100, 2) : 0
            ],
            'request_pool' => [
                'size' => count($this->requestPool),
                'hits' => $this->requestHits,
                'misses' => $this->requestMisses,
                'hit_ratio' => $totalRequestRequests > 0 ?
                    round(($this->requestHits / $totalRequestRequests) * 100, 2) : 0
            ],
            'json_cache' => [
                'size' => count($this->jsonCache),
                'hits' => $this->jsonCacheHits,
                'misses' => $this->jsonCacheMisses,
                'hit_ratio' => $totalJsonRequests > 0 ?
                    round(($this->jsonCacheHits / $totalJsonRequests) * 100, 2) : 0
            ],
            'header_cache' => [
                'size' => count($this->headerCache)
            ],
            'memory_efficiency' => [
                'max_pool_size' => $this->maxPoolSize,
                'max_cache_size' => $this->maxCacheSize,
                'memory_usage' => memory_get_usage(true)
            ]
        ];
    }

    /**
     * Set pool configuration
     */
    public function configure(int $maxPoolSize = 1000, int $maxCacheSize = 5000): void
    {
        $this->maxPoolSize = $maxPoolSize;
        $this->maxCacheSize = $maxCacheSize;
    }
}
