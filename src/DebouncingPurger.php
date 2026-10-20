<?php

declare(strict_types=1);

namespace Contenir\Cdn;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

use function array_unique;
use function array_values;

/**
 * Merges the purges asked for within a few seconds of each other into one
 * request, so an action that changes several things purges once. A purge of
 * everything takes in any URLs waiting with it.
 *
 * The first purge asked for waits. Those that follow join it until $seconds
 * have passed since it, and then the next one sends them all. Whatever still
 * waits is sent by flush(), which runs when the purger is destroyed, at the
 * latest when the request ends.
 *
 * @api
 */
final class DebouncingPurger implements PurgerInterface
{
    private ?DateTimeImmutable $since = null;

    /** @var list<string>|null The URLs waiting, or null for everything */
    private ?array $urls = [];

    public function __construct(
        private readonly PurgerInterface $purger,
        private readonly ClockInterface $clock,
        private readonly int $seconds = 5,
    ) {}

    /**
     * Sends whatever is waiting, now.
     */
    public function flush(): void
    {
        $urls        = $this->urls;
        $this->since = null;
        $this->urls  = [];

        if (null === $urls) {
            $this->purger->purgeEverything();

            return;
        }

        if ([] !== $urls) {
            $this->purger->purge($urls);
        }
    }

    #[Override]
    public function purge(array $urls): void
    {
        if ([] === $urls) {
            return;
        }

        if (null !== $this->urls) {
            $this->urls = array_values(array_unique([...$this->urls, ...$urls]));
        }

        $this->wait();
    }

    #[Override]
    public function purgeEverything(): void
    {
        $this->urls = null;

        $this->wait();
    }

    private function wait(): void
    {
        $now         = $this->clock->now();
        $this->since ??= $now;

        if (($now->getTimestamp() - $this->since->getTimestamp()) >= $this->seconds) {
            $this->flush();
        }
    }

    public function __destruct()
    {
        $this->flush();
    }
}
