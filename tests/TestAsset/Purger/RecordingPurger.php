<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\TestAsset\Purger;

use Contenir\Cdn\PurgerInterface;
use Override;

/**
 * A purger that keeps each purge it is asked for: a list of URLs, or
 * "everything".
 */
final class RecordingPurger implements PurgerInterface
{
    /** @var list<list<string>|'everything'> */
    public array $purges = [];

    #[Override]
    public function purge(array $urls): void
    {
        $this->purges[] = $urls;
    }

    #[Override]
    public function purgeEverything(): void
    {
        $this->purges[] = 'everything';
    }
}
