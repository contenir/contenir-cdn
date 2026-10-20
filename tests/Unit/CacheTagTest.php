<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit;

use Closure;
use Contenir\Cdn\CacheTag;
use Contenir\Cdn\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CacheTagTest extends TestCase
{
    /**
     * @return array<string, array{Closure(): CacheTag, string}>
     */
    public static function tags(): array
    {
        return [
            'the site'                        => [CacheTag::site(...), 'c-site'],
            'a section'                       => [
                static fn(): CacheTag => CacheTag::section('library.news.articles'),
                'c-sec-library.news.articles',
            ],
            'a record'                        => [
                static fn(): CacheTag => CacheTag::record('library.news.articles', 12),
                'c-rec-library.news.articles-12',
            ],
            'a record with a string id'       => [
                static fn(): CacheTag => CacheTag::record('pages', 'about'),
                'c-rec-pages-about',
            ],
            'lower-cased and trimmed'         => [
                static fn(): CacheTag => CacheTag::section(' News_Items '),
                'c-sec-news_items',
            ],
            'other characters as one _'       => [
                static fn(): CacheTag => CacheTag::section('news & events'),
                'c-sec-news_events',
            ],
            'a hyphen only between the parts' => [
                static fn(): CacheTag => CacheTag::record('case-studies', 'a-b'),
                'c-rec-case_studies-a_b',
            ],
        ];
    }

    #[Test]
    #[DataProvider('tags')]
    public function namesTheTag(Closure $make, string $expected): void
    {
        $tag = $make();

        static::assertSame([$expected, $expected], [$tag->value, (string) $tag]);
    }

    #[Test]
    public function refusesABlankRecordId(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because(
            'A cache tag needs a section name, and an id for a record',
        ));

        CacheTag::record('pages', '');
    }

    #[Test]
    public function refusesABlankSection(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because(
            'A cache tag needs a section name, and an id for a record',
        ));

        CacheTag::section('  ');
    }
}
