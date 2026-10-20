<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\ApiResponse;
use Contenir\Cdn\Cloudflare\Client;
use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Exception\InvalidArgumentException;
use Contenir\Cdn\Exception\TransportException;
use Contenir\Cdn\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Cdn\Tests\TestAsset\Factory\ZoneFactory;
use Contenir\Cdn\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Cdn\Tests\TestAsset\Http\NetworkError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;
use function range;

#[Group('unit')]
final class ClientTest extends TestCase
{
    /**
     * @return array{string, string, string}
     */
    private static function sent(RequestInterface $request): array
    {
        return [$request->getMethod(), (string) $request->getUri(), (string) $request->getBody()];
    }

    /**
     * @return list<string>
     */
    private static function urls(int $count): array
    {
        return array_map(static fn(int $n): string => "https://example.com/{$n}", range(1, $count));
    }

    #[Test]
    public function givesItsCredentials(): void
    {
        $credentials = Credentials::token('token-1');

        static::assertSame($credentials, ZoneFactory::client(new FakeHttpClient(), $credentials)->credentials());
    }

    #[Test]
    public function purgesAHundredUrlsInOneRequest(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());

        $response = ZoneFactory::client($http)->purgeUrls(ZoneFactory::id(), self::urls(100));

        static::assertTrue($response->success);
    }

    #[Test]
    public function purgesEverything(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());

        $response = ZoneFactory::client($http)->purgeEverything(ZoneFactory::id());

        static::assertSame(
            [
                true,
                [
                    'POST',
                    'https://api.cloudflare.com/client/v4/zones/' . ZoneFactory::ID . '/purge_cache',
                    '{"purge_everything":true}',
                ],
            ],
            [$response->success, self::sent($http->requests[0])],
        );
    }

    #[Test]
    public function purgesTags(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());

        ZoneFactory::client($http)->purgeTags(ZoneFactory::id(), ['c-sec-news', 'c-rec-news-1']);

        static::assertSame(
            [
                'POST',
                'https://api.cloudflare.com/client/v4/zones/' . ZoneFactory::ID . '/purge_cache',
                '{"tags":["c-sec-news","c-rec-news-1"]}',
            ],
            self::sent($http->requests[0]),
        );
    }

    #[Test]
    public function purgesUrlsAsFiles(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());

        ZoneFactory::client($http)->purgeUrls(ZoneFactory::id(), [
            'https://example.com/a?b=1',
            'https://example.com/a?b=1',
        ]);

        static::assertSame('{"files":["https://example.com/a?b=1"]}', (string) $http->requests[0]->getBody());
    }

    #[Test]
    public function readsAZone(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::zone());

        $response = ZoneFactory::client($http)->zone(ZoneFactory::id());

        static::assertSame(
            ['GET', 'https://api.cloudflare.com/client/v4/zones/' . ZoneFactory::ID, '', 'example.com'],
            [...self::sent($http->requests[0]), $response->result['name'] ?? null],
        );
    }

    #[Test]
    public function refusesMoreThanAHundredUrls(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because('One purge names 1 to 100 URLs'));

        ZoneFactory::client(new FakeHttpClient())->purgeUrls(ZoneFactory::id(), self::urls(101));
    }

    #[Test]
    public function refusesNoTags(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because('One purge names 1 to 100 tags'));

        ZoneFactory::client(new FakeHttpClient())->purgeTags(ZoneFactory::id(), []);
    }

    #[Test]
    public function refusesNoUrls(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because('One purge names 1 to 100 URLs'));

        ZoneFactory::client(new FakeHttpClient())->purgeUrls(ZoneFactory::id(), [' ']);
    }

    #[Test]
    public function returnsARefusal(): void
    {
        $response = ZoneFactory::client(new FakeHttpClient(CloudflareResponses::refused(
            10_000,
            'Authentication error',
        )))
            ->purgeEverything(ZoneFactory::id());

        static::assertInstanceOf(ApiResponse::class, $response);
        static::assertSame('10000 Authentication error', $response->reason());
    }

    #[Test]
    public function sendsJsonOnlyWithABody(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok(), CloudflareResponses::ok());

        $client = ZoneFactory::client($http);
        $client->purgeEverything(ZoneFactory::id());
        $client->zone(ZoneFactory::id());

        static::assertSame(['application/json', ''], [
            $http->requests[0]->getHeaderLine('Content-Type'),
            $http->requests[1]->getHeaderLine('Content-Type'),
        ]);
    }

    #[Test]
    public function signsInWithTheGlobalKey(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());

        ZoneFactory::client($http, ZoneFactory::globalKey())->zone(ZoneFactory::id());

        static::assertSame(['ops@example.com', ZoneFactory::KEY, ''], [
            $http->requests[0]->getHeaderLine('X-Auth-Email'),
            $http->requests[0]->getHeaderLine('X-Auth-Key'),
            $http->requests[0]->getHeaderLine('Authorization'),
        ]);
    }

    #[Test]
    public function signsInWithTheToken(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());

        ZoneFactory::client($http)->zone(ZoneFactory::id());

        static::assertSame(
            ['Bearer ' . ZoneFactory::TOKEN, '', 'application/json'],
            [
                $http->requests[0]->getHeaderLine('Authorization'),
                $http->requests[0]->getHeaderLine('X-Auth-Key'),
                $http->requests[0]->getHeaderLine('Accept'),
            ],
        );
    }

    #[Test]
    public function throwsWhenCloudflareCantBeReachedWithoutTheToken(): void
    {
        $http = new FakeHttpClient(new NetworkError('Could not resolve host; token ' . ZoneFactory::TOKEN));

        $this->expectExceptionObject(TransportException::because(
            'Could not reach Cloudflare: Could not resolve host; token [redacted]',
        ));

        ZoneFactory::client($http)->verify();
    }

    #[Test]
    public function usesAnotherAddressForTheApi(): void
    {
        $http    = new FakeHttpClient(CloudflareResponses::ok());
        $factory = new Psr17Factory();

        (new Client($http, $factory, $factory, Credentials::token('t'), 'https://cf.test/v4/'))->verify();

        static::assertSame('https://cf.test/v4/user/tokens/verify', (string) $http->requests[0]->getUri());
    }

    #[Test]
    public function verifiesAGlobalKeyByReadingTheUser(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());

        ZoneFactory::client($http, ZoneFactory::globalKey())->verify();

        static::assertSame('https://api.cloudflare.com/client/v4/user', (string) $http->requests[0]->getUri());
    }

    #[Test]
    public function verifiesAToken(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok(['status' => 'active']));

        ZoneFactory::client($http)->verify();

        static::assertSame(
            ['GET', 'https://api.cloudflare.com/client/v4/user/tokens/verify', ''],
            self::sent($http->requests[0]),
        );
    }
}
