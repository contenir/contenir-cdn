<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

/**
 * What testing the connection found: the zone, when Cloudflare let the
 * credentials read it, or the problem, in words an editor can act on.
 *
 * @api
 */
final readonly class Connection
{
    private function __construct(
        public ?Zone $zone,
        public ?string $problem,
        public bool $deprecatedKey,
        public ?string $requestId,
    ) {}

    public static function failed(string $problem, bool $deprecatedKey, ?string $requestId = null): self
    {
        return new self(null, $problem, $deprecatedKey, $requestId);
    }

    public static function to(Zone $zone, bool $deprecatedKey, ?string $requestId = null): self
    {
        return new self($zone, null, $deprecatedKey, $requestId);
    }

    public function succeeded(): bool
    {
        return null === $this->problem;
    }
}
