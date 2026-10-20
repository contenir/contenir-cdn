<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit;

use Contenir\Cdn\UrlList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UrlListTest extends TestCase
{
    private const array HOSTS = ['example.com', ' WWW.Example.com '];

    /**
     * @return array<string, array{string, string}>
     */
    public static function refused(): array
    {
        return [
            'a path'                 => ['/news/', 'Enter the full address, starting https://'],
            'no scheme'              => ['example.com/news/', 'Enter the full address, starting https://'],
            'another scheme'         => ['ftp://example.com/file.txt', 'Enter the full address, starting https://'],
            'a scheme with no host'  => ['https:///news/', 'Enter the full address, starting https://'],
            'another site'           => [
                'https://example.org/news/',
                'example.org is not one of this site\'s addresses',
            ],
            'a subdomain not listed' => [
                'https://cdn.example.com/a.jpg',
                'cdn.example.com is not one of this site\'s addresses',
            ],
        ];
    }

    #[Test]
    public function acceptsEverySubdomainOfAWildcardHost(): void
    {
        $list = UrlList::fromText(
            "https://www.example.com/a\nhttps://cdn.assets.example.com/b\nhttps://example.com/c\nhttps://badexample.com/d",
            ['*.example.com'],
        );

        static::assertSame(
            [
                ['https://www.example.com/a', 'https://cdn.assets.example.com/b'],
                [
                    'https://example.com/c'    => 'example.com is not one of this site\'s addresses',
                    'https://badexample.com/d' => 'badexample.com is not one of this site\'s addresses',
                ],
            ],
            [$list->urls, $list->refused],
        );
    }

    #[Test]
    public function acceptsTheSitesOwnAddressesOnePerLine(): void
    {
        $list = UrlList::fromText(
            "https://example.com/news/\r\nhttp://www.example.com/a.jpg?w=200\rHTTPS://WWW.EXAMPLE.COM/About",
            self::HOSTS,
        );

        static::assertSame([
            ['https://example.com/news/', 'http://www.example.com/a.jpg?w=200', 'HTTPS://WWW.EXAMPLE.COM/About'],
            [],
        ], [$list->urls, $list->refused]);
    }

    #[Test]
    #[DataProvider('refused')]
    public function refusesWhatTheSiteCantPurge(string $line, string $reason): void
    {
        $list = UrlList::fromText($line, self::HOSTS);

        static::assertSame([[], [$line => $reason]], [$list->urls, $list->refused]);
    }

    #[Test]
    public function skipsBlankLinesAndRepeats(): void
    {
        $list = UrlList::fromText("\n  https://example.com/  \n\nhttps://example.com/\n/a\n/a\n", self::HOSTS);

        static::assertSame([['https://example.com/'], ['/a' => 'Enter the full address, starting https://']], [
            $list->urls,
            $list->refused,
        ]);
    }

    #[Test]
    public function treatsAHostEndingLikeAnotherAsADifferentHost(): void
    {
        static::assertSame([], UrlList::fromText('https://www.example.com/', ['example.com', 'ample.com'])->urls);
    }
}
