<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\TestAsset\Factory;

use Contenir\Cdn\Cloudflare\Client;
use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Cdn\Tests\TestAsset\Http\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * A zone, and clients for it that answer from a FakeHttpClient.
 */
final class ZoneFactory
{
    public const string ID = '023e105f4ecef8ad9ca31a8372d0c353';

    /**
     * @mago-expect lint:no-literal-password A made-up token for tests, which the fake HTTP client answers.
     */
    public const string TOKEN = 'Y2xvdWRmbGFyZS10b2tlbi1zZWNyZXQ';

    public const string KEY = 'c2547eb745079dac9320b638f5e225cf483cc5cfdda41';

    public static function client(FakeHttpClient $http, ?Credentials $credentials = null): Client
    {
        $factory = new Psr17Factory();

        return new Client($http, $factory, $factory, $credentials ?? Credentials::token(self::TOKEN));
    }

    public static function globalKey(): Credentials
    {
        return Credentials::globalKey('ops@example.com', self::KEY);
    }

    public static function id(): ZoneId
    {
        return ZoneId::parse(self::ID);
    }
}
