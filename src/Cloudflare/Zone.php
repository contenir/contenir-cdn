<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use function is_array;
use function is_string;

/**
 * A zone as Cloudflare's API describes it: its domain, status and plan.
 *
 * @api
 */
final readonly class Zone
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public string $plan,
    ) {}

    /**
     * Reads the result of a zone request.
     *
     * @param array<array-key, mixed> $result
     */
    public static function fromResult(array $result): self
    {
        $plan = is_array($result['plan'] ?? null) ? $result['plan'] : [];

        return new self(
            self::text($result, 'id'),
            self::text($result, 'name'),
            self::text($result, 'status'),
            self::text($plan, 'name'),
        );
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $key): string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : '';
    }

    /**
     * How the zone's cache can be purged: every way, on every plan.
     *
     * @return list<PurgeMethod>
     */
    public function purgeMethods(): array
    {
        return PurgeMethod::cases();
    }
}
