<?php

declare(strict_types=1);

namespace Contenir\Cdn\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Thrown when a value can't be used: a blank cache tag, a malformed zone ID,
 * or too many URLs for one request.
 *
 * @api
 */
final class InvalidArgumentException extends BaseInvalidArgumentException implements ExceptionInterface
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
