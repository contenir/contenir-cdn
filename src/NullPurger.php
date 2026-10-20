<?php

declare(strict_types=1);

namespace Contenir\Cdn;

use Override;

/**
 * The purger for a site with no CDN, or with its CDN switched off: it purges
 * nothing.
 *
 * @api
 */
final readonly class NullPurger implements PurgerInterface
{
    #[Override]
    public function purge(array $urls): void {}

    #[Override]
    public function purgeEverything(): void {}
}
