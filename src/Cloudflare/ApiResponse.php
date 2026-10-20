<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use Psr\Http\Message\ResponseInterface;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function is_array;
use function is_bool;
use function json_decode;
use function sprintf;

/**
 * Cloudflare's answer to one API request: whether it did what was asked, its
 * errors, its result, and the request's id (the cf-ray header), which
 * Cloudflare's support asks for.
 *
 * @api
 */
final readonly class ApiResponse
{
    /**
     * @param list<ApiError>          $errors
     * @param array<array-key, mixed> $result
     */
    public function __construct(
        public int $status,
        public bool $success,
        public array $errors = [],
        public array $result = [],
        public ?string $requestId = null,
    ) {}

    /**
     * Reads an HTTP response from the API. One that isn't the API's JSON,
     * such as an error page from a proxy, is a failure with the HTTP status
     * as its reason.
     */
    public static function fromHttp(ResponseInterface $response): self
    {
        $status    = $response->getStatusCode();
        $requestId = $response->getHeaderLine('cf-ray');
        $requestId = '' === $requestId ? null : $requestId;

        /** @var mixed $body */
        $body = json_decode((string) $response->getBody(), associative: true);
        if (! is_array($body) || ! is_bool($body['success'] ?? null)) {
            return new self(
                $status,
                false,
                [
                    new ApiError(0, sprintf('Cloudflare answered HTTP %d without an API response', $status)),
                ],
                [],
                $requestId,
            );
        }

        return new self(
            $status,
            $body['success'] && $status >= 200 && $status < 300,
            array_values(array_filter(array_map(ApiError::fromApi(...), self::list($body, 'errors')))),
            self::list($body, 'result'),
            $requestId,
        );
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    private static function list(array $body, string $key): array
    {
        return is_array($body[$key] ?? null) ? $body[$key] : [];
    }

    /**
     * The errors, joined by semicolons, or "no reason given" with the HTTP
     * status.
     */
    public function reason(): string
    {
        $reason = implode('; ', $this->errors);

        return '' === $reason ? sprintf('no reason given (HTTP %d)', $this->status) : $reason;
    }
}
