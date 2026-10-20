<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\ApiError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ApiErrorTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?ApiError}>
     */
    public static function entries(): array
    {
        return [
            'an error'          => [
                ['code' => 10_000, 'message' => 'Authentication error'],
                new ApiError(10_000, 'Authentication error'),
            ],
            'no code'           => [['message' => 'Bad request'], new ApiError(0, 'Bad request')],
            'a code as text'    => [['code' => '1012', 'message' => 'x'], new ApiError(0, 'x')],
            'no message'        => [['code' => 1012], new ApiError(1012, '')],
            'a message as list' => [['code' => 1012, 'message' => ['x']], new ApiError(1012, '')],
            'not an entry'      => ['Authentication error', null],
        ];
    }

    #[Test]
    #[DataProvider('entries')]
    public function readsAnEntryOfTheErrors(mixed $entry, ?ApiError $expected): void
    {
        static::assertEquals($expected, ApiError::fromApi($entry));
    }

    #[Test]
    public function readsAsItsCodeAndMessage(): void
    {
        static::assertSame(
            ['10000 Authentication error', 'Bad request', '1012'],
            [
                (string) new ApiError(10_000, 'Authentication error'),
                (string) new ApiError(0, 'Bad request'),
                (string) new ApiError(1012, ''),
            ],
        );
    }
}
