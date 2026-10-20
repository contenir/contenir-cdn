<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Cdn\Exception\InvalidArgumentException;
use Contenir\Cdn\Tests\TestAsset\Factory\ZoneFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ZoneIdTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function notZoneIds(): array
    {
        return [
            'blank'                => [''],
            'too short'            => ['023e105f4ecef8ad9ca31a8372d0c35'],
            'too long'             => ['023e105f4ecef8ad9ca31a8372d0c3531'],
            'not hexadecimal'      => ['023e105f4ecef8ad9ca31a8372d0c35g'],
            'a path'               => ['023e105f4ecef8ad9ca31a8372d0c353/../x'],
            'text after a newline' => ["023e105f4ecef8ad9ca31a8372d0c353\nx"],
        ];
    }

    #[Test]
    public function keepsAZoneIdTrimmedAndLowerCased(): void
    {
        $id = ZoneId::parse(' 023E105F4ECEF8AD9CA31A8372D0C353 ');

        static::assertSame([ZoneFactory::ID, ZoneFactory::ID], [$id->value, (string) $id]);
    }

    #[Test]
    #[DataProvider('notZoneIds')]
    public function refusesWhatIsntAZoneId(string $value): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because(
            'A zone ID is 32 letters and digits. Copy it from the zone\'s Overview page in Cloudflare',
        ));

        ZoneId::parse($value);
    }

    #[Test]
    #[DataProvider('notZoneIds')]
    public function triesWhatIsntAZoneIdAsNone(string $value): void
    {
        static::assertNull(ZoneId::tryParse($value));
    }
}
