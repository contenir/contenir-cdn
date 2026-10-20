<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

/**
 * The ways Cloudflare can purge a zone's cache. Since April 2025 every plan
 * has all of them; plans differ only in how many a minute they take, and
 * Enterprise in how many URLs, tags, hosts or prefixes one request names.
 * The cases run from the broadest purge to the narrowest.
 *
 * @api
 */
enum PurgeMethod: string
{
    case Everything = 'everything';
    case Urls       = 'urls';
    case Tags       = 'tags';
    case Hostnames  = 'hostnames';
    case Prefixes   = 'prefixes';

    public function label(): string
    {
        return match ($this) {
            self::Everything => 'everything',
            self::Hostnames => 'by hostname',
            self::Prefixes => 'by prefix',
            self::Tags => 'by cache tag',
            self::Urls => 'by URL',
        };
    }
}
