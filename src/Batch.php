<?php

declare(strict_types=1);

namespace Contenir\Cdn;

use Contenir\Cdn\Exception\InvalidArgumentException;

use function array_chunk;
use function array_filter;
use function array_map;
use function array_unique;
use function sprintf;

/**
 * Splits the URLs or tags for a purge into the requests a CDN takes them in:
 * each named once, none blank, at most MAX to a request.
 *
 * @api
 */
final class Batch
{
    /**
     * The most URLs or tags one request names. Cloudflare takes 100 on every
     * plan but Enterprise, which takes 500.
     */
    public const int MAX = 100;

    /**
     * @param list<string> $items
     *
     * @return list<non-empty-list<string>>
     *
     * @throws InvalidArgumentException When $size is below 1 or above MAX.
     */
    public static function of(array $items, int $size = self::MAX): array
    {
        if ($size < 1 || $size > self::MAX) {
            throw InvalidArgumentException::because(sprintf('A batch holds 1 to %d items, not %d', self::MAX, $size));
        }

        $items = array_unique(array_filter(
            array_map(trim(...), $items),
            static fn(string $item): bool => '' !== $item,
        ));

        /** @var list<non-empty-list<string>> */
        return array_chunk($items, $size);
    }
}
