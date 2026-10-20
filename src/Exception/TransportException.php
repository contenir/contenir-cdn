<?php

declare(strict_types=1);

namespace Contenir\Cdn\Exception;

use RuntimeException;

/**
 * Thrown when a CDN's API could not be reached at all: no answer, rather than
 * an answer refusing the request. It carries only a message, with any
 * credential taken out, and not the HTTP client's own exception, whose
 * request may hold one.
 *
 * @api
 */
final class TransportException extends RuntimeException implements ExceptionInterface
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
