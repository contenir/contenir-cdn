<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\PurgeMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class PurgeMethodTest extends TestCase
{
    #[Test]
    public function namesEachWay(): void
    {
        static::assertSame(
            ['everything', 'by hostname', 'by prefix', 'by cache tag', 'by URL'],
            array_map(static fn(PurgeMethod $method): string => $method->label(), PurgeMethod::cases()),
        );
    }
}
