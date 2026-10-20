<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Integration;

use Contenir\Cdn\Cloudflare\CloudflarePurger;
use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\DebouncingPurger;
use Contenir\Cdn\Tests\TestAsset\Clock\SettableClock;
use Contenir\Cdn\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Cdn\Tests\TestAsset\Factory\ZoneFactory;
use Contenir\Cdn\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Cdn\Tests\TestAsset\Log\MemoryLogger;
use Contenir\Cdn\UrlList;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_keys;

/**
 * The pieces together, as a CMS uses them: credentials chosen from settings,
 * a Cloudflare purger behind a debouncer, and an editor's list of URLs.
 * Cloudflare itself is a fake HTTP client.
 */
#[Group('integration')]
final class PurgingThroughCloudflareTest extends TestCase
{
    #[Test]
    public function anActionsPurgesReachCloudflareAsOneRequest(): void
    {
        $http   = new FakeHttpClient(CloudflareResponses::ok(ray: 'ray-1'));
        $log    = new MemoryLogger();
        $purger = new CloudflarePurger(
            ZoneFactory::client($http, Credentials::choose(ZoneFactory::TOKEN, 'ops@example.com', ZoneFactory::KEY)),
            ZoneFactory::id(),
            $log,
            'content',
        );
        $debounced = new DebouncingPurger($purger, new SettableClock());

        $debounced->purge(['https://example.com/a.jpg']);
        $debounced->purgeEverything();
        $debounced->purge(['https://example.com/b.jpg']);
        $debounced->flush();

        static::assertSame(
            [
                ['{"purge_everything":true}', 'Bearer ' . ZoneFactory::TOKEN],
                [['content', 'everything', 'purged', 'ray-1']],
            ],
            [
                [(string) $http->requests[0]->getBody(), $http->requests[0]->getHeaderLine('Authorization')],
                [[
                    $log->entries[0]['context']['trigger'],
                    $log->entries[0]['context']['scope'],
                    $log->entries[0]['context']['result'],
                    $log->entries[0]['context']['request_id'],
                ]],
            ],
        );
    }

    #[Test]
    public function anEditorsListPurgesOnlyTheSitesUrls(): void
    {
        $http   = new FakeHttpClient(CloudflareResponses::ok());
        $log    = new MemoryLogger();
        $purger = new CloudflarePurger(ZoneFactory::client($http), ZoneFactory::id(), $log, 'person');
        $list   = UrlList::fromText("https://example.com/news/\nhttps://example.org/news/", ['example.com']);

        $report = $purger->purgeUrls($list->urls);

        static::assertSame(
            [true, 1, ['https://example.org/news/'], [['https://example.com/news/']]],
            [
                $report->succeeded(),
                $report->count,
                array_keys($list->refused),
                array_column(array_column($log->entries, 'context'), 'targets'),
            ],
        );
    }
}
