<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\TestAsset\Http;

use Nyholm\Psr7\Request;
use Override;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * What a PSR-18 client throws when the network fails.
 */
final class NetworkError extends RuntimeException implements NetworkExceptionInterface
{
    #[Override]
    public function getRequest(): RequestInterface
    {
        return new Request('GET', 'https://api.cloudflare.com/client/v4');
    }
}
