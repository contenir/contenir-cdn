<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\ApiError;
use Contenir\Cdn\Cloudflare\ApiResponse;
use Contenir\Cdn\Tests\TestAsset\Factory\CloudflareResponses;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ApiResponseTest extends TestCase
{
    #[Test]
    public function aPageThatIsntTheApisIsAFailure(): void
    {
        static::assertEquals(
            new ApiResponse(502, false, [new ApiError(0, 'Cloudflare answered HTTP 502 without an API response')]),
            ApiResponse::fromHttp(CloudflareResponses::page(502)),
        );
    }

    #[Test]
    public function aSuccessAtEitherEndOfTheTwoHundredsIsOne(): void
    {
        static::assertSame([true, true], [
            ApiResponse::fromHttp(CloudflareResponses::json(200, ['success' => true]))->success,
            ApiResponse::fromHttp(CloudflareResponses::json(299, ['success' => true]))->success,
        ]);
    }

    #[Test]
    public function aSuccessOutsideTwoHundredsIsAFailure(): void
    {
        static::assertSame([false, false, false], [
            ApiResponse::fromHttp(CloudflareResponses::json(199, ['success' => true]))->success,
            ApiResponse::fromHttp(CloudflareResponses::json(300, ['success' => true]))->success,
            ApiResponse::fromHttp(CloudflareResponses::json(200, ['success' => false]))->success,
        ]);
    }

    #[Test]
    public function givesItsErrorsAsTheReason(): void
    {
        $response = new ApiResponse(400, false, [new ApiError(1012, 'Bad files'), new ApiError(0, 'Try again')]);

        static::assertSame('1012 Bad files; Try again', $response->reason());
    }

    #[Test]
    public function jsonWithoutASuccessFlagIsntTheApis(): void
    {
        static::assertSame(
            'Cloudflare answered HTTP 200 without an API response',
            ApiResponse::fromHttp(CloudflareResponses::json(200, ['success' => 'yes']))->reason(),
        );
    }

    #[Test]
    public function readsAnAnswerWithoutARequestId(): void
    {
        static::assertNull(ApiResponse::fromHttp(CloudflareResponses::ok(ray: ''))->requestId);
    }

    #[Test]
    public function readsARefusalWithItsErrors(): void
    {
        static::assertEquals(
            new ApiResponse(403, false, [new ApiError(10_000, 'Authentication error')], [], CloudflareResponses::RAY),
            ApiResponse::fromHttp(CloudflareResponses::refused(10_000, 'Authentication error')),
        );
    }

    #[Test]
    public function readsASuccess(): void
    {
        static::assertEquals(
            new ApiResponse(200, true, [], ['id' => 'abc'], CloudflareResponses::RAY),
            ApiResponse::fromHttp(CloudflareResponses::ok(['id' => 'abc'])),
        );
    }

    #[Test]
    public function readsErrorsAndResultThatArentListsAsEmpty(): void
    {
        $response = ApiResponse::fromHttp(CloudflareResponses::json(200, [
            'success' => true,
            'errors'  => 'none',
            'result'  => 'abc',
        ]));

        static::assertSame([[], []], [$response->errors, $response->result]);
    }

    #[Test]
    public function saysWhenNoReasonIsGiven(): void
    {
        static::assertSame('no reason given (HTTP 429)', (new ApiResponse(429, false))->reason());
    }

    #[Test]
    public function skipsErrorsThatArentEntries(): void
    {
        $response = ApiResponse::fromHttp(CloudflareResponses::json(400, [
            'success' => false,
            'errors'  => ['text', ['code' => 1012, 'message' => 'Bad files']],
        ]));

        static::assertEquals([new ApiError(1012, 'Bad files')], $response->errors);
    }
}
