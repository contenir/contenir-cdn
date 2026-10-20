<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\PurgeMethod;
use Contenir\Cdn\Cloudflare\Zone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ZoneTest extends TestCase
{
    #[Test]
    public function canBePurgedEveryWay(): void
    {
        static::assertSame(
            PurgeMethod::cases(),
            (new Zone('abc', 'example.com', 'active', 'Free Website'))->purgeMethods(),
        );
    }

    #[Test]
    public function readsAZoneResult(): void
    {
        static::assertEquals(
            new Zone('abc', 'example.com', 'active', 'Free Website'),
            Zone::fromResult([
                'id'     => 'abc',
                'name'   => 'example.com',
                'status' => 'active',
                'plan'   => ['id' => '0feeeeeeeeeeeeeeeeeeeeeeeeeeeeee', 'name' => 'Free Website'],
            ]),
        );
    }

    #[Test]
    public function readsWhatIsMissingOrNotTextAsBlank(): void
    {
        static::assertEquals(new Zone('', '', '', ''), Zone::fromResult(['id' => 12, 'plan' => 'Pro']));
    }
}
