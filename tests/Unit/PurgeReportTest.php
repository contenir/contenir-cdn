<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit;

use Contenir\Cdn\PurgeReport;
use Contenir\Cdn\Scope;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PurgeReportTest extends TestCase
{
    #[Test]
    public function failsWithAProblem(): void
    {
        static::assertFalse((new PurgeReport(Scope::Everything, 0, [], ['Could not reach Cloudflare']))->succeeded());
    }

    #[Test]
    public function joinsTheRequestsOfOnePurge(): void
    {
        $report = (new PurgeReport(Scope::Urls, 100, ['ray-1']))->and(new PurgeReport(
            Scope::Urls,
            20,
            ['ray-2'],
            ['Cloudflare refused: 1012 Too many files'],
        ));

        static::assertEquals(
            new PurgeReport(Scope::Urls, 120, ['ray-1', 'ray-2'], ['Cloudflare refused: 1012 Too many files']),
            $report,
        );
    }

    #[Test]
    public function succeedsWithoutProblems(): void
    {
        static::assertTrue((new PurgeReport(Scope::Everything))->succeeded());
    }
}
