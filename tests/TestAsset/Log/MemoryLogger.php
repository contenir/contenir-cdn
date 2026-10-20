<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\TestAsset\Log;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A PSR-3 logger that keeps its entries.
 */
final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $entries = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->entries[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
