<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use Contenir\Cdn\Batch;
use Contenir\Cdn\Exception\ExceptionInterface;
use Contenir\Cdn\PurgeReport;
use Contenir\Cdn\PurgerInterface;
use Contenir\Cdn\Scope;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function count;
use function sprintf;

/**
 * Purges one zone's cache through Cloudflare's API, in requests of at most
 * Batch::MAX URLs, and logs every request: what started it (the trigger),
 * what it dropped (the scope), how many URLs, whether Cloudflare purged,
 * refused or couldn't be reached (the result), and Cloudflare's id for it.
 *
 * It never throws. purge() and purgeEverything() are best effort, for a CMS
 * that has already made the change; purgeUrls() and purgeAll() also return a
 * report, for someone who asked for the purge and is waiting to hear.
 *
 * @api
 */
final readonly class CloudflarePurger implements PurgerInterface
{
    /**
     * @param string $trigger What starts purges through this purger, for the log: a content change, an asset, a person
     */
    public function __construct(
        private Client $client,
        private ZoneId $zone,
        private LoggerInterface $log = new NullLogger(),
        private string $trigger = 'cms',
    ) {}

    #[Override]
    public function purge(array $urls): void
    {
        $this->purgeUrls($urls);
    }

    /**
     * Drops everything Cloudflare caches for the zone.
     */
    public function purgeAll(): PurgeReport
    {
        return $this->send(Scope::Everything, []);
    }

    #[Override]
    public function purgeEverything(): void
    {
        $this->purgeAll();
    }

    /**
     * Drops these URLs, in as many requests as they need. A request Cloudflare
     * refuses doesn't stop the rest.
     *
     * @param list<string> $urls
     */
    public function purgeUrls(array $urls): PurgeReport
    {
        $report = new PurgeReport(Scope::Urls);
        foreach (Batch::of($urls) as $batch) {
            $report = $report->and($this->send(Scope::Urls, $batch));
        }

        return $report;
    }

    /**
     * The same purger, logging $trigger as what started its purges.
     */
    public function withTrigger(string $trigger): self
    {
        return new self($this->client, $this->zone, $this->log, $trigger);
    }

    /**
     * @param list<string> $targets
     *
     * @throws ExceptionInterface When Cloudflare can't be reached.
     */
    private function request(Scope $scope, array $targets): ApiResponse
    {
        return Scope::Everything === $scope
            ? $this->client->purgeEverything($this->zone)
            : $this->client->purgeUrls($this->zone, $targets);
    }

    /**
     * @param list<string> $targets
     */
    private function send(Scope $scope, array $targets): PurgeReport
    {
        $what    = Scope::Everything === $scope ? 'everything' : sprintf('%d URLs', count($targets));
        $context = [
            'trigger' => $this->trigger,
            'scope'   => $scope->value,
            'count'   => count($targets),
            'targets' => $targets,
            'zone'    => $this->zone->value,
        ];

        try {
            $response = $this->request($scope, $targets);
        } catch (ExceptionInterface $exception) {
            $problem = $this->client->credentials()->redact($exception->getMessage());
            $this->log->error('Could not purge {what} from Cloudflare for zone {zone}: {error}', [
                ...$context,
                'what'       => $what,
                'result'     => 'failed',
                'request_id' => null,
                'error'      => $problem,
            ]);

            return new PurgeReport($scope, count($targets), [], [$problem]);
        }

        $context = [...$context, 'what' => $what, 'request_id' => $response->requestId, 'status' => $response->status];
        $ids     = null === $response->requestId ? [] : [$response->requestId];
        if (! $response->success) {
            $problem = $this->client->credentials()->redact($response->reason());
            $this->log->error('Cloudflare refused to purge {what} for zone {zone}: {error}', [
                ...$context,
                'result' => 'rejected',
                'error'  => $problem,
            ]);

            return new PurgeReport($scope, count($targets), $ids, [sprintf('Cloudflare refused: %s', $problem)]);
        }

        $this->log->info('Purged {what} from Cloudflare for zone {zone}', [...$context, 'result' => 'purged']);

        return new PurgeReport($scope, count($targets), $ids);
    }
}
