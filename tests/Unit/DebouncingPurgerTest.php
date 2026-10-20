<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit;

use Contenir\Cdn\DebouncingPurger;
use Contenir\Cdn\Tests\TestAsset\Clock\SettableClock;
use Contenir\Cdn\Tests\TestAsset\Purger\RecordingPurger;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DebouncingPurgerTest extends TestCase
{
    private SettableClock $clock;

    private RecordingPurger $purger;

    #[Test]
    public function aPurgeOfEverythingTakesInTheUrlsWaiting(): void
    {
        $debounced = $this->debounced();
        $debounced->purge(['https://example.com/a']);
        $debounced->purgeEverything();
        $debounced->purge(['https://example.com/b']);
        $debounced->flush();

        static::assertSame(['everything'], $this->purger->purges);
    }

    #[Test]
    public function flushingSendsNothingWhenNothingWaits(): void
    {
        $this->debounced()->flush();

        static::assertSame([], $this->purger->purges);
    }

    #[Test]
    public function holdsTheFirstPurge(): void
    {
        $debounced = $this->debounced();
        $debounced->purge(['https://example.com/a']);

        static::assertSame([], $this->purger->purges);
    }

    #[Test]
    public function ignoresAnEmptyPurge(): void
    {
        $debounced = $this->debounced();
        $debounced->purge([]);
        $this->clock->advance(10);
        $debounced->purge(['https://example.com/a']);

        static::assertSame([], $this->purger->purges);
    }

    #[Test]
    public function mergesPurgesWithinTheWindowIntoOne(): void
    {
        $debounced = $this->debounced();
        $debounced->purge(['https://example.com/a', 'https://example.com/b']);
        $this->clock->advance(4);
        $debounced->purge(['https://example.com/b', 'https://example.com/c']);
        $debounced->flush();

        static::assertSame(
            [['https://example.com/a', 'https://example.com/b', 'https://example.com/c']],
            $this->purger->purges,
        );
    }

    #[Test]
    public function sendsEverythingOnceTheWindowHasPassed(): void
    {
        $debounced = $this->debounced();
        $debounced->purgeEverything();
        $this->clock->advance(5);
        $debounced->purgeEverything();

        static::assertSame(['everything'], $this->purger->purges);
    }

    #[Test]
    public function sendsWhatWaitsOnceTheWindowHasPassed(): void
    {
        $debounced = $this->debounced();
        $debounced->purge(['https://example.com/a']);
        $this->clock->advance(5);
        $debounced->purge(['https://example.com/b']);
        $debounced->purge(['https://example.com/c']);

        static::assertSame([['https://example.com/a', 'https://example.com/b']], $this->purger->purges);
    }

    #[Test]
    public function sendsWhatWaitsWhenItGoes(): void
    {
        $debounced = $this->debounced();
        $debounced->purge(['https://example.com/a']);
        unset($debounced);

        static::assertSame([['https://example.com/a']], $this->purger->purges);
    }

    #[Test]
    public function startsANewWindowAfterSending(): void
    {
        $debounced = $this->debounced(seconds: 2);
        $debounced->purge(['https://example.com/a']);
        $this->clock->advance(2);
        $debounced->purge(['https://example.com/b']);
        $this->clock->advance(1);
        $debounced->purge(['https://example.com/c']);

        static::assertSame([['https://example.com/a', 'https://example.com/b']], $this->purger->purges);
    }

    #[Test]
    public function waitsFiveSecondsByDefault(): void
    {
        $debounced = new DebouncingPurger($this->purger, $this->clock);
        $debounced->purge(['https://example.com/a']);
        $this->clock->advance(4);
        $debounced->purge(['https://example.com/b']);
        $held = $this->purger->purges;
        $this->clock->advance(1);
        $debounced->purge(['https://example.com/c']);

        static::assertSame([[], [['https://example.com/a', 'https://example.com/b', 'https://example.com/c']]], [
            $held,
            $this->purger->purges,
        ]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->clock  = new SettableClock();
        $this->purger = new RecordingPurger();
    }

    private function debounced(int $seconds = 5): DebouncingPurger
    {
        return new DebouncingPurger($this->purger, $this->clock, $seconds);
    }
}
