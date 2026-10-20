<?php

declare(strict_types=1);

namespace Contenir\Cdn;

/**
 * What a purge drops: everything, some URLs, or the pages carrying some
 * cache tags.
 *
 * @api
 */
enum Scope: string
{
    case Everything = 'everything';
    case Tags       = 'tags';
    case Urls       = 'urls';
}
