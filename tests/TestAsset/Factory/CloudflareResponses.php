<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\TestAsset\Factory;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Answers from Cloudflare's API, as its documentation shows them.
 */
final class CloudflareResponses
{
    public const string RAY = '8d3f2a1b4c5e6f70-SYD';

    /**
     * @param array<string, mixed> $body
     */
    public static function json(int $status, array $body, string $ray = self::RAY): ResponseInterface
    {
        $headers = '' === $ray ? [] : ['cf-ray' => $ray];

        return new Response(
            $status,
            ['Content-Type' => 'application/json', ...$headers],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    public static function ok(array $result = ['id' => ZoneFactory::ID], string $ray = self::RAY): ResponseInterface
    {
        return self::json(200, ['success' => true, 'errors' => [], 'messages' => [], 'result' => $result], $ray);
    }

    public static function page(int $status, string $html = '<html>Bad gateway</html>'): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'text/html'], $html);
    }

    public static function refused(
        int $code,
        string $message,
        int $status = 403,
        string $ray = self::RAY,
    ): ResponseInterface {
        return self::json(
            status: $status,
            body: [
                'success'  => false,
                'errors'   => [['code' => $code, 'message' => $message]],
                'messages' => [],
                'result'   => null,
            ],
            ray: $ray,
        );
    }

    /**
     * A zone, as GET /zones/{id} gives it.
     */
    public static function zone(string $plan = 'Pro Plan'): ResponseInterface
    {
        return self::ok([
            'id'     => ZoneFactory::ID,
            'name'   => 'example.com',
            'status' => 'active',
            'plan'   => ['id' => 'abc', 'name' => $plan],
        ]);
    }
}
