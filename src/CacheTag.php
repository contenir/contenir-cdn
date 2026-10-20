<?php

declare(strict_types=1);

namespace Contenir\Cdn;

use Contenir\Cdn\Exception\InvalidArgumentException;
use Override;
use Stringable;

use function preg_replace;
use function strtolower;
use function trim;

/**
 * A cache tag a public page carries, so a purge can name the pages showing a
 * record instead of their URLs:
 *
 * - c-site on every page;
 * - c-sec-<section> on every page showing a record of the section, listings
 *   included;
 * - c-rec-<section>-<id> on every page showing that record.
 *
 * Section names and ids are lower-cased, and anything but letters, digits,
 * dots and underscores becomes an underscore, so a hyphen only ever separates
 * the parts.
 *
 * @api
 */
final readonly class CacheTag implements Stringable
{
    public const string PREFIX = 'c-';

    private function __construct(
        public string $value,
    ) {}

    /**
     * The tag on every page showing $id of $section.
     *
     * @throws InvalidArgumentException When the section or id is blank.
     */
    public static function record(string $section, int|string $id): self
    {
        return new self(self::PREFIX . 'rec-' . self::part($section) . '-' . self::part((string) $id));
    }

    /**
     * The tag on every page showing a record of $section.
     *
     * @throws InvalidArgumentException When the section is blank.
     */
    public static function section(string $section): self
    {
        return new self(self::PREFIX . 'sec-' . self::part($section));
    }

    /**
     * The tag on every page of the site.
     */
    public static function site(): self
    {
        return new self(self::PREFIX . 'site');
    }

    /**
     * @throws InvalidArgumentException When $value is blank.
     */
    private static function part(string $value): string
    {
        $part = (string) preg_replace('/[^a-z0-9._]+/', replacement: '_', subject: strtolower(trim($value)));
        if ('' === $part) {
            throw InvalidArgumentException::because('A cache tag needs a section name, and an id for a record');
        }

        return $part;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
