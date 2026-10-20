<?php

declare(strict_types=1);

namespace Contenir\Cdn;

/**
 * Whatever caches a site's public pages in front of it: drop some of them, or
 * drop everything. A CMS calls this, never a CDN's own client, so a site with
 * no CDN purges nothing and a second CDN is a second implementation.
 *
 * Purging is best effort. An implementation reports a failure (to its log)
 * and returns, since the change that asked for the purge has already been
 * made; it never throws.
 *
 * @api
 */
interface PurgerInterface
{
    /**
     * Drops these full public URLs from the CDN's cache.
     *
     * @param list<string> $urls
     */
    public function purge(array $urls): void;

    /**
     * Drops everything the CDN holds for the site.
     */
    public function purgeEverything(): void;
}
