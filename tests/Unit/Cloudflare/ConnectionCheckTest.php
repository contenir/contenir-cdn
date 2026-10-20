<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\Connection;
use Contenir\Cdn\Cloudflare\ConnectionCheck;
use Contenir\Cdn\Cloudflare\Zone;
use Contenir\Cdn\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Cdn\Tests\TestAsset\Factory\ZoneFactory;
use Contenir\Cdn\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Cdn\Tests\TestAsset\Http\NetworkError;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConnectionCheckTest extends TestCase
{
    #[Test]
    public function readsTheZoneWithAnActiveToken(): void
    {
        $http = new FakeHttpClient(
            CloudflareResponses::ok(['id' => 'tok', 'status' => 'active']),
            CloudflareResponses::zone('Business Plan'),
        );

        static::assertEquals(
            Connection::to(
                new Zone(ZoneFactory::ID, 'example.com', 'active', 'Business Plan'),
                false,
                CloudflareResponses::RAY,
            ),
            $this->check($http),
        );
    }

    #[Test]
    public function readsTheZoneWithTheDeprecatedGlobalKey(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok([
            'id'    => 'user',
            'email' => 'ops@example.com',
        ]), CloudflareResponses::zone());

        $connection = (new ConnectionCheck(ZoneFactory::client($http, ZoneFactory::globalKey())))->run(
            ZoneFactory::id(),
        );

        static::assertSame([true, true, 'Pro Plan'], [
            $connection->succeeded(),
            $connection->deprecatedKey,
            $connection->zone?->plan,
        ]);
    }

    #[Test]
    public function saysWhenCloudflareCantBeReached(): void
    {
        $http = new FakeHttpClient(new NetworkError('Connection timed out'));

        static::assertEquals(
            Connection::failed('Could not reach Cloudflare: Connection timed out', false),
            $this->check($http),
        );
    }

    #[Test]
    public function stopsWhenCloudflareRefusesTheGlobalKey(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::refused(9103, 'Unknown X-Auth-Key or X-Auth-Email', 403));

        $connection = (new ConnectionCheck(ZoneFactory::client($http, ZoneFactory::globalKey())))->run(
            ZoneFactory::id(),
        );

        static::assertSame(
            'Cloudflare refused the email and Global API Key: 9103 Unknown X-Auth-Key or X-Auth-Email',
            $connection->problem,
        );
    }

    #[Test]
    public function stopsWhenCloudflareRefusesTheToken(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::refused(1000, 'Invalid API Token', 401, 'ray-401'));

        static::assertEquals(
            Connection::failed('Cloudflare refused the API token: 1000 Invalid API Token', false, 'ray-401'),
            $this->check($http),
        );
    }

    #[Test]
    public function stopsWhenTheTokenIsntActive(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok(['id' => 'tok', 'status' => 'disabled'], 'ray-2'));

        static::assertEquals(
            Connection::failed('The API token is disabled. Make it active in Cloudflare', false, 'ray-2'),
            $this->check($http),
        );
    }

    #[Test]
    public function stopsWhenTheTokensStatusIsntText(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok(['status' => 12]));

        static::assertSame('The API token is not active. Make it active in Cloudflare', $this->check($http)->problem);
    }

    #[Test]
    public function stopsWhenTheZoneCantBeRead(): void
    {
        $http = new FakeHttpClient(
            CloudflareResponses::ok(['status' => 'active']),
            CloudflareResponses::refused(9109, 'Unauthorized to access requested resource', 403, 'ray-3'),
        );

        static::assertEquals(
            Connection::failed(
                'The API token can\'t read zone '
                    . ZoneFactory::ID
                    . ': 9109 Unauthorized to access requested resource. '
                    . 'Check the zone ID, and give the token Zone Read as well as Cache Purge',
                false,
                'ray-3',
            ),
            $this->check($http),
        );
    }

    private function check(FakeHttpClient $http): Connection
    {
        return (new ConnectionCheck(ZoneFactory::client($http)))->run(ZoneFactory::id());
    }
}
