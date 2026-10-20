<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\TestAsset\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock that tells the time it is set to, and moves only when told.
 */
final class SettableClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-10-21 09:00:00'),
    ) {}

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
