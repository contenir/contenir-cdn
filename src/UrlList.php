<?php

declare(strict_types=1);

namespace Contenir\Cdn;

use function array_key_exists;
use function array_map;
use function explode;
use function in_array;
use function is_string;
use function parse_url;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * URLs an editor typed to be purged, one to a line, sorted into those the
 * site may purge and those it may not. A CDN caches a URL by its full
 * address, so each must be absolute (http or https) and on one of the site's
 * own hosts. Blank lines are skipped and repeats kept once.
 *
 * @api
 */
final readonly class UrlList
{
    /**
     * @param list<string>          $urls    The URLs to purge, in the order given
     * @param array<string, string> $refused Each refused line, and why
     */
    private function __construct(
        public array $urls,
        public array $refused,
    ) {}

    /**
     * @param list<string> $hosts The site's hosts, such as www.example.com
     */
    public static function fromText(string $text, array $hosts): self
    {
        $hosts   = array_map(static fn(string $host): string => strtolower(trim($host)), $hosts);
        $urls    = [];
        $refused = [];
        foreach (explode("\n", str_replace("\r", replace: "\n", subject: $text)) as $line) {
            $url = trim($line);
            if ('' === $url || in_array($url, $urls, strict: true) || array_key_exists($url, $refused)) {
                continue;
            }

            $problem = self::problem($url, $hosts);
            if (null === $problem) {
                $urls[] = $url;
                continue;
            }

            $refused[$url] = $problem;
        }

        return new self($urls, $refused);
    }

    /**
     * @param list<string> $hosts
     */
    private static function problem(string $url, array $hosts): ?string
    {
        $scheme = parse_url($url, component: PHP_URL_SCHEME);
        $host   = parse_url($url, component: PHP_URL_HOST);
        if (
            ! is_string($scheme)
            || ! in_array(strtolower($scheme), ['http', 'https'], strict: true)
            || ! is_string($host)
        ) {
            return 'Enter the full address, starting https://';
        }

        if (! in_array(strtolower($host), $hosts, strict: true)) {
            return sprintf('%s is not one of this site\'s addresses', $host);
        }

        return null;
    }
}
