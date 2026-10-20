<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use Override;
use Stringable;

use function is_array;
use function is_int;
use function is_string;
use function trim;

/**
 * One error in an answer from Cloudflare's API: its code, such as 10000 for
 * an authentication error, and its message.
 *
 * @api
 */
final readonly class ApiError implements Stringable
{
    public function __construct(
        public int $code,
        public string $message,
    ) {}

    /**
     * Reads one entry of an answer's errors, or null when it isn't one.
     */
    public static function fromApi(mixed $error): ?self
    {
        if (! is_array($error)) {
            return null;
        }

        return new self(
            is_int($error['code'] ?? null) ? $error['code'] : 0,
            is_string($error['message'] ?? null) ? $error['message'] : '',
        );
    }

    #[Override]
    public function __toString(): string
    {
        return trim((0 === $this->code ? '' : "{$this->code} ") . $this->message);
    }
}
