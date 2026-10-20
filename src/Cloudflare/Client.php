<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use Contenir\Cdn\Batch;
use Contenir\Cdn\Exception\InvalidArgumentException;
use Contenir\Cdn\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function count;
use function json_encode;
use function rtrim;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Cloudflare's API, for the requests purging needs: checking the credentials,
 * reading a zone, and purging its cache. It sends through any PSR-18 HTTP
 * client, so tests never reach Cloudflare.
 *
 * An answer refusing a request is returned like any other; only a request
 * that gets no answer at all throws.
 *
 * @api
 */
final readonly class Client
{
    public const string API = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
        private Credentials $credentials,
        private string $api = self::API,
    ) {}

    /**
     * @param list<string> $items
     *
     * @return non-empty-list<string>
     *
     * @throws InvalidArgumentException When there are none or too many.
     */
    private static function batch(array $items, string $what): array
    {
        $batches = Batch::of($items);
        if (1 !== count($batches)) {
            throw InvalidArgumentException::because(sprintf('One purge names 1 to %d %s', Batch::MAX, $what));
        }

        return $batches[0];
    }

    /**
     * The credentials the client signs in with.
     */
    public function credentials(): Credentials
    {
        return $this->credentials;
    }

    /**
     * Drops everything Cloudflare caches for the zone.
     *
     * @throws TransportException When Cloudflare can't be reached.
     */
    public function purgeEverything(ZoneId $zone): ApiResponse
    {
        return $this->send('POST', sprintf('/zones/%s/purge_cache', $zone), ['purge_everything' => true]);
    }

    /**
     * Drops the pages carrying these cache tags: 1 to Batch::MAX of them.
     *
     * @param list<string> $tags
     *
     * @throws InvalidArgumentException When there are none or too many.
     * @throws TransportException When Cloudflare can't be reached.
     */
    public function purgeTags(ZoneId $zone, array $tags): ApiResponse
    {
        return $this->send('POST', sprintf('/zones/%s/purge_cache', $zone), ['tags' => self::batch($tags, 'tags')]);
    }

    /**
     * Drops these URLs: 1 to Batch::MAX of them.
     *
     * @param list<string> $urls
     *
     * @throws InvalidArgumentException When there are none or too many.
     * @throws TransportException When Cloudflare can't be reached.
     */
    public function purgeUrls(ZoneId $zone, array $urls): ApiResponse
    {
        return $this->send('POST', sprintf('/zones/%s/purge_cache', $zone), ['files' => self::batch($urls, 'URLs')]);
    }

    /**
     * Checks the credentials: an API token is verified, and the Global API
     * Key's account is read.
     *
     * @throws TransportException When Cloudflare can't be reached.
     */
    public function verify(): ApiResponse
    {
        return $this->send('GET', $this->credentials->isToken() ? '/user/tokens/verify' : '/user');
    }

    /**
     * Reads the zone: its name, status and plan.
     *
     * @throws TransportException When Cloudflare can't be reached.
     */
    public function zone(ZoneId $zone): ApiResponse
    {
        return $this->send('GET', sprintf('/zones/%s', $zone));
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @throws TransportException When Cloudflare can't be reached.
     */
    private function send(string $method, string $path, ?array $body = null): ApiResponse
    {
        $request = $this->requests
            ->createRequest($method, rtrim($this->api, characters: '/') . $path)
            ->withHeader('Accept', 'application/json');
        foreach ($this->credentials->headers() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if (null !== $body) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody($this->streams->createStream(json_encode(
                    $body,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                )));
        }

        try {
            return ApiResponse::fromHttp($this->http->sendRequest($request));
        } catch (ClientExceptionInterface $exception) {
            throw TransportException::because($this->credentials->redact(sprintf(
                'Could not reach Cloudflare: %s',
                $exception->getMessage(),
            )));
        }
    }
}
