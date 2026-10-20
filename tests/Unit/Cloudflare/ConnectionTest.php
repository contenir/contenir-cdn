<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\Connection;
use Contenir\Cdn\Cloudflare\Zone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConnectionTest extends TestCase
{
    #[Test]
    public function aConnectionToAZoneSucceeded(): void
    {
        $zone       = new Zone('abc', 'example.com', 'active', 'Pro Plan');
        $connection = Connection::to($zone, false, 'ray-1');

        static::assertSame([true, $zone, null, false, 'ray-1'], [
            $connection->succeeded(),
            $connection->zone,
            $connection->problem,
            $connection->deprecatedKey,
            $connection->requestId,
        ]);
    }

    #[Test]
    public function aFailedConnectionHasItsProblem(): void
    {
        $connection = Connection::failed('Cloudflare refused the API token', true);

        static::assertSame([false, null, 'Cloudflare refused the API token', true, null], [
            $connection->succeeded(),
            $connection->zone,
            $connection->problem,
            $connection->deprecatedKey,
            $connection->requestId,
        ]);
    }
}
