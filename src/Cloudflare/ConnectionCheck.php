<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use Contenir\Cdn\Exception\TransportException;

use function is_string;
use function sprintf;

/**
 * Tests the connection to Cloudflare without purging anything: it checks
 * the credentials, then reads the zone for its plan.
 *
 * Reading the zone needs the token to hold Zone Read as well as Cache Purge.
 * Only a purge proves Cache Purge itself, so the first one does.
 *
 * @api
 */
final readonly class ConnectionCheck
{
    public function __construct(
        private Client $client,
    ) {}

    public function run(ZoneId $zone): Connection
    {
        $credentials = $this->client->credentials();
        $deprecated  = ! $credentials->isToken();

        try {
            $verified = $this->client->verify();
            if (! $verified->success) {
                return Connection::failed(
                    sprintf('Cloudflare refused the %s: %s', $credentials->describe(), $verified->reason()),
                    $deprecated,
                    $verified->requestId,
                );
            }

            /** @var mixed $status */
            $status = $verified->result['status'] ?? 'active';
            if ('active' !== $status) {
                return Connection::failed(
                    sprintf(
                        'The API token is %s. Make it active in Cloudflare',
                        is_string($status) ? $status : 'not active',
                    ),
                    $deprecated,
                    $verified->requestId,
                );
            }

            $read = $this->client->zone($zone);
            if (! $read->success) {
                return Connection::failed(
                    sprintf(
                        'The %s can\'t read zone %s: %s. Check the zone ID, and give the token Zone Read as well as Cache Purge',
                        $credentials->describe(),
                        $zone,
                        $read->reason(),
                    ),
                    $deprecated,
                    $read->requestId,
                );
            }

            return Connection::to(Zone::fromResult($read->result), $deprecated, $read->requestId);
        } catch (TransportException $exception) {
            return Connection::failed($exception->getMessage(), $deprecated);
        }
    }
}
