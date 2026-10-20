<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit;

use Contenir\Cdn\Batch;
use Contenir\Cdn\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function range;

#[Group('unit')]
final class BatchTest extends TestCase
{
    /**
     * @return array<string, array{int}>
     */
    public static function badSizes(): array
    {
        return [
            'none'     => [0],
            'too many' => [101],
        ];
    }

    #[Test]
    public function holdsAtMostAHundredToARequest(): void
    {
        $urls = array_map(static fn(int $n): string => "https://example.com/{$n}", range(
            start: 1,
            end: 250,
        ));

        static::assertSame([100, 100, 50], array_map(count(...), Batch::of($urls)));
    }

    #[Test]
    public function keepsTheOrderGiven(): void
    {
        static::assertSame([['b', 'a'], ['c']], Batch::of(['b', 'a', 'c'], 2));
    }

    #[Test]
    public function makesNoRequestForNothing(): void
    {
        static::assertSame([], Batch::of(['', ' ']));
    }

    #[Test]
    public function namesEachOnceAndSkipsBlanks(): void
    {
        static::assertSame([['a', 'b']], Batch::of([' a ', '', 'b', 'a', '  ']));
    }

    #[Test]
    #[DataProvider('badSizes')]
    public function refusesASizeOutsideTheLimit(int $size): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because("A batch holds 1 to 100 items, not {$size}"));

        Batch::of(['a'], $size);
    }

    #[Test]
    public function takesOneOrAHundred(): void
    {
        static::assertSame([[['a'], ['b']], [['a', 'b']]], [Batch::of(['a', 'b'], 1), Batch::of(['a', 'b'], 100)]);
    }
}
