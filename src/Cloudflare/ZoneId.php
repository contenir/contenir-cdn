<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use Contenir\Cdn\Exception\InvalidArgumentException;
use Override;
use Stringable;

use function preg_match;
use function strtolower;
use function trim;

/**
 * A Cloudflare zone's ID: 32 hexadecimal characters, as the zone's Overview
 * page shows it under API.
 *
 * @api
 */
final readonly class ZoneId implements Stringable
{
    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidArgumentException When $value isn't a zone ID.
     */
    public static function parse(string $value): self
    {
        return (
            self::tryParse($value) ?? throw InvalidArgumentException::because(
                'A zone ID is 32 letters and digits. Copy it from the zone\'s Overview page in Cloudflare',
            )
        );
    }

    /**
     * The zone ID, or null when $value isn't one.
     */
    public static function tryParse(string $value): ?self
    {
        $id = strtolower(trim($value));

        return 1 === preg_match('/^[0-9a-f]{32}$/', $id) ? new self($id) : null;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
