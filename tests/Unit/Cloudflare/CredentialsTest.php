<?php

declare(strict_types=1);

namespace Contenir\Cdn\Tests\Unit\Cloudflare;

use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

use function print_r;

#[Group('unit')]
final class CredentialsTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string, ?string}>
     */
    public static function notSetUp(): array
    {
        return [
            'nothing'          => [null, null, null],
            'blanks'           => [' ', ' ', ' '],
            'an email, no key' => [null, 'ops@example.com', ''],
            'a key, no email'  => ['', null, 'key'],
            'a blank email'    => [null, ' ', 'key'],
            'a blank key'      => [null, 'ops@example.com', ' '],
        ];
    }

    #[Test]
    public function aGlobalKeySignsInWithTheEmail(): void
    {
        $credentials = Credentials::globalKey(' ops@example.com ', ' key-1 ');

        static::assertSame(
            [false, ['X-Auth-Email' => 'ops@example.com', 'X-Auth-Key' => 'key-1'], 'email and Global API Key'],
            [$credentials->isToken(), $credentials->headers(), $credentials->describe()],
        );
    }

    #[Test]
    public function aTokenSignsInAsBearer(): void
    {
        $credentials = Credentials::token(' token-1 ');

        static::assertSame([true, ['Authorization' => 'Bearer token-1'], 'API token'], [
            $credentials->isToken(),
            $credentials->headers(),
            $credentials->describe(),
        ]);
    }

    #[Test]
    #[DataProvider('notSetUp')]
    public function choosesNothingWhenCloudflareIsntSetUp(
        #[SensitiveParameter]
        ?string $token,
        ?string $email,
        #[SensitiveParameter]
        ?string $key,
    ): void {
        static::assertNull(Credentials::choose($token, $email, $key));
    }

    #[Test]
    public function choosesTheTokenOverTheGlobalKey(): void
    {
        static::assertEquals(
            Credentials::token('token-1'),
            Credentials::choose(' token-1 ', 'ops@example.com', 'key-1'),
        );
    }

    #[Test]
    public function fallsBackToTheGlobalKeyWithoutAToken(): void
    {
        static::assertEquals(
            Credentials::globalKey('ops@example.com', 'key-1'),
            Credentials::choose(' ', 'ops@example.com', 'key-1'),
        );
    }

    #[Test]
    public function redactsTheGlobalKeyButNotTheEmail(): void
    {
        static::assertSame(
            'ops@example.com, [redacted]',
            Credentials::globalKey('ops@example.com', 'key-1')->redact('ops@example.com, key-1'),
        );
    }

    #[Test]
    public function redactsTheToken(): void
    {
        static::assertSame(
            'Bad header Bearer [redacted]',
            Credentials::token('token-1')->redact('Bad header Bearer token-1'),
        );
    }

    #[Test]
    public function refusesABlankToken(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because('An API token is needed'));

        Credentials::token(' ');
    }

    #[Test]
    public function refusesAGlobalKeyWithoutTheEmail(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because(
            'Signing in with a Global API Key needs the account\'s email too',
        ));

        Credentials::globalKey(' ', 'key-1');
    }

    #[Test]
    public function refusesAnEmailWithoutTheGlobalKey(): void
    {
        $this->expectExceptionObject(InvalidArgumentException::because(
            'Signing in with a Global API Key needs the account\'s email too',
        ));

        Credentials::globalKey('ops@example.com', ' ');
    }

    #[Test]
    public function showsNeitherSecretWhenDumped(): void
    {
        static::assertSame(
            [
                "Contenir\\Cdn\\Cloudflare\\Credentials Object\n(\n    [method] => API token\n)\n",
                "Contenir\\Cdn\\Cloudflare\\Credentials Object\n(\n    [method] => email and Global API Key\n)\n",
            ],
            [
                print_r(Credentials::token('token-1'), return: true),
                print_r(Credentials::globalKey('ops@example.com', 'key-1'), return: true),
            ],
        );
    }
}
