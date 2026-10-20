<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\CloudflarePurger;
use Contenir\Cdn\PurgeReport;
use Contenir\Cdn\Scope;
use Contenir\Cdn\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Cdn\Tests\TestAsset\Factory\ZoneFactory;
use Contenir\Cdn\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Cdn\Tests\TestAsset\Http\NetworkError;
use Contenir\Cdn\Tests\TestAsset\Log\MemoryLogger;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

use function array_column;
use function array_map;
use function count;
use function json_decode;
use function range;

#[Group('unit')]
final class CloudflarePurgerTest extends TestCase
{
    private MemoryLogger $log;

    /**
     * @return list<string>
     */
    private static function urls(int $count): array
    {
        return array_map(static fn(int $n): string => "https://example.com/{$n}", range(1, $count));
    }

    #[Test]
    public function logsARefusalAndCarriesOn(): void
    {
        $http = new FakeHttpClient(
            CloudflareResponses::refused(
                1012,
                'Request must contain one of "purge_everything", "files", "tags"',
                400,
                'ray-1',
            ),
            CloudflareResponses::ok(ray: 'ray-2'),
        );

        $report = $this->purger($http)->purgeUrls(self::urls(101));

        static::assertEquals(
            [
                new PurgeReport(
                    Scope::Urls,
                    101,
                    ['ray-1', 'ray-2'],
                    [
                        'Cloudflare refused: 1012 Request must contain one of "purge_everything", "files", "tags"',
                    ],
                ),
                [
                    LogLevel::ERROR,
                    'Cloudflare refused to purge {what} for zone {zone}: {error}',
                    'rejected',
                    'ray-1',
                    400,
                ],
                [LogLevel::INFO, 'purged'],
            ],
            [
                $report,
                [
                    $this->log->entries[0]['level'],
                    $this->log->entries[0]['message'],
                    $this->log->entries[0]['context']['result'],
                    $this->log->entries[0]['context']['request_id'],
                    $this->log->entries[0]['context']['status'],
                ],
                [$this->log->entries[1]['level'], $this->log->entries[1]['context']['result']],
            ],
        );
    }

    #[Test]
    public function logsARefusalWithoutARequestId(): void
    {
        $report = $this->purger(new FakeHttpClient(CloudflareResponses::page(502)))->purgeAll();

        static::assertEquals(
            new PurgeReport(
                Scope::Everything,
                0,
                [],
                ['Cloudflare refused: Cloudflare answered HTTP 502 without an API response'],
            ),
            $report,
        );
    }

    #[Test]
    public function logsCmsAsTheTriggerByDefault(): void
    {
        (new CloudflarePurger(
            ZoneFactory::client(new FakeHttpClient(CloudflareResponses::ok())),
            ZoneFactory::id(),
            $this->log,
        ))->purgeAll();

        static::assertSame('cms', $this->log->entries[0]['context']['trigger']);
    }

    #[Test]
    public function logsTheTriggerItIsGiven(): void
    {
        $purger = $this->purger(new FakeHttpClient(CloudflareResponses::ok(), CloudflareResponses::ok()));

        $purger->withTrigger('asset')->purgeAll();
        $purger->purgeAll();

        static::assertSame(['asset', 'content'], array_column(array_column($this->log->entries, 'context'), 'trigger'));
    }

    #[Test]
    public function logsTheUrlsItPurged(): void
    {
        $this->purger(new FakeHttpClient(CloudflareResponses::ok()))->purgeUrls(['https://example.com/a']);

        static::assertSame(
            ['urls', 1, ['https://example.com/a']],
            [
                $this->log->entries[0]['context']['scope'],
                $this->log->entries[0]['context']['count'],
                $this->log->entries[0]['context']['targets'],
            ],
        );
    }

    #[Test]
    public function logsWhenCloudflareCantBeReached(): void
    {
        $http = new FakeHttpClient(new NetworkError('Connection timed out'));

        $report = $this->purger($http)->purgeUrls(['https://example.com/a']);

        static::assertEquals(
            [
                new PurgeReport(Scope::Urls, 1, [], ['Could not reach Cloudflare: Connection timed out']),
                [
                    'level'   => LogLevel::ERROR,
                    'message' => 'Could not purge {what} from Cloudflare for zone {zone}: {error}',
                    'context' => [
                        'trigger'    => 'content',
                        'scope'      => 'urls',
                        'count'      => 1,
                        'targets'    => ['https://example.com/a'],
                        'zone'       => ZoneFactory::ID,
                        'what'       => '1 URLs',
                        'result'     => 'failed',
                        'request_id' => null,
                        'error'      => 'Could not reach Cloudflare: Connection timed out',
                    ],
                ],
            ],
            [$report, $this->log->entries[0]],
        );
    }

    #[Test]
    public function makesNoRequestForNoUrls(): void
    {
        $http = new FakeHttpClient();

        $report = $this->purger($http)->purgeUrls(['', ' ']);

        static::assertEquals([new PurgeReport(Scope::Urls), [], []], [$report, $http->requests, $this->log->entries]);
    }

    #[Test]
    public function neverLogsTheToken(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::refused(
            6003,
            'Invalid request headers: ' . ZoneFactory::TOKEN,
        ));

        $report = $this->purger($http)->purgeAll();

        static::assertSame(
            [
                'Cloudflare refused: 6003 Invalid request headers: [redacted]',
                '6003 Invalid request headers: [redacted]',
            ],
            [$report->problems[0], $this->log->entries[0]['context']['error']],
        );
    }

    #[Test]
    public function purgesAsTheInterfaceAsks(): void
    {
        $http   = new FakeHttpClient(CloudflareResponses::ok(), CloudflareResponses::ok());
        $purger = $this->purger($http);

        $purger->purge(['https://example.com/a']);
        $purger->purgeEverything();

        static::assertSame(['{"files":["https://example.com/a"]}', '{"purge_everything":true}'], [
            (string) $http->requests[0]->getBody(),
            (string) $http->requests[1]->getBody(),
        ]);
    }

    #[Test]
    public function purgesEverythingAndLogsIt(): void
    {
        $report = $this->purger(new FakeHttpClient(CloudflareResponses::ok()))->purgeAll();

        static::assertEquals(
            [
                new PurgeReport(Scope::Everything, 0, [CloudflareResponses::RAY]),
                [
                    'level'   => LogLevel::INFO,
                    'message' => 'Purged {what} from Cloudflare for zone {zone}',
                    'context' => [
                        'trigger'    => 'content',
                        'scope'      => 'everything',
                        'count'      => 0,
                        'targets'    => [],
                        'zone'       => ZoneFactory::ID,
                        'what'       => 'everything',
                        'request_id' => CloudflareResponses::RAY,
                        'status'     => 200,
                        'result'     => 'purged',
                    ],
                ],
            ],
            [$report, $this->log->entries[0]],
        );
    }

    #[Test]
    public function purgesUrlsAHundredAtATime(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok(ray: 'ray-1'), CloudflareResponses::ok(ray: 'ray-2'));

        $report = $this->purger($http)->purgeUrls(self::urls(150));

        static::assertEquals(
            [new PurgeReport(Scope::Urls, 150, ['ray-1', 'ray-2']), [100, 50], ['100 URLs', '50 URLs']],
            [
                $report,
                array_map(
                    static fn($request): int => count(
                        (array) (json_decode((string) $request->getBody(), associative: true)['files'] ?? []),
                    ),
                    $http->requests,
                ),
                array_map(static fn(array $entry): mixed => $entry['context']['what'], $this->log->entries),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->log = new MemoryLogger();
    }

    private function purger(FakeHttpClient $http): CloudflarePurger
    {
        return new CloudflarePurger(ZoneFactory::client($http), ZoneFactory::id(), $this->log, 'content');
    }
}
