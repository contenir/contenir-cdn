<?php

declare(strict_types=1);

namespace Contenir\Cdn;

/**
 * How a purge went: what it dropped, how many URLs or tags it named, the
 * CDN's id for each request it made, and each problem, in words an editor
 * can act on. A purge with no problems succeeded.
 *
 * @api
 */
final readonly class PurgeReport
{
    /**
     * @param int          $count      How many URLs or tags it named; 0 for everything
     * @param list<string> $requestIds The CDN's id for each request, where it gave one
     * @param list<string> $problems   Why each request that failed failed
     */
    public function __construct(
        public Scope $scope,
        public int $count = 0,
        public array $requestIds = [],
        public array $problems = [],
    ) {}

    /**
     * This report and $other as one: a purge made in several requests.
     */
    public function and(self $other): self
    {
        return new self(
            $this->scope,
            $this->count + $other->count,
            [...$this->requestIds, ...$other->requestIds],
            [...$this->problems, ...$other->problems],
        );
    }

    public function succeeded(): bool
    {
        return [] === $this->problems;
    }
}
