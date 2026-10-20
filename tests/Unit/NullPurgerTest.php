<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit;

use Contenir\Cdn\NullPurger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class NullPurgerTest extends TestCase
{
    #[Test]
    public function purgesNothingAndNeverFails(): void
    {
        $this->expectNotToPerformAssertions();

        $purger = new NullPurger();
        $purger->purge(['https://example.com/']);
        $purger->purgeEverything();
    }
}
