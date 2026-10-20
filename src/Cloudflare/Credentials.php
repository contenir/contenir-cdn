<?php

declare(strict_types=1);

namespace Contenir\Cdn\Cloudflare;

use Contenir\Cdn\Exception\InvalidArgumentException;
use SensitiveParameter;

use function str_replace;
use function trim;

/**
 * How the client signs in to Cloudflare's API: an API token, or, only for a
 * site that has no token yet, the account's email and Global API Key. A token
 * can be limited to purging one zone's cache; the Global API Key can do
 * anything the account can, so it is deprecated here.
 *
 * Neither secret is ever shown: var_dump() and print_r() name only the
 * method, and redact() takes them out of any text.
 *
 * @api
 */
final readonly class Credentials
{
    private const string REDACTED = '[redacted]';

    private function __construct(
        #[SensitiveParameter]
        private string $token,
        private string $email,
        #[SensitiveParameter]
        private string $key,
    ) {}

    /**
     * A token when there is one, else the email and Global API Key when both
     * are there, else null: Cloudflare isn't set up.
     */
    public static function choose(
        #[SensitiveParameter]
        ?string $token,
        ?string $email,
        #[SensitiveParameter]
        ?string $key,
    ): ?self {
        if ('' !== trim((string) $token)) {
            return self::token((string) $token);
        }

        return '' === trim((string) $email) || '' === trim((string) $key)
            ? null
            : self::globalKey((string) $email, (string) $key);
    }

    /**
     * The account's email and Global API Key: deprecated, for a site with no
     * token yet.
     *
     * @throws InvalidArgumentException When either is blank.
     */
    public static function globalKey(
        string $email,
        #[SensitiveParameter]
        string $key,
    ): self {
        if ('' === trim($email) || '' === trim($key)) {
            throw InvalidArgumentException::because('Signing in with a Global API Key needs the account\'s email too');
        }

        return new self('', trim($email), trim($key));
    }

    /**
     * An API token.
     *
     * @throws InvalidArgumentException When the token is blank.
     */
    public static function token(
        #[SensitiveParameter]
        string $token,
    ): self {
        if ('' === trim($token)) {
            throw InvalidArgumentException::because('An API token is needed');
        }

        return new self(trim($token), '', '');
    }

    /**
     * What these are, for a message: "API token", or "email and Global API
     * Key".
     */
    public function describe(): string
    {
        return $this->isToken() ? 'API token' : 'email and Global API Key';
    }

    /**
     * The headers that sign a request in.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        if ($this->isToken()) {
            return ['Authorization' => "Bearer {$this->token}"];
        }

        return ['X-Auth-Email' => $this->email, 'X-Auth-Key' => $this->key];
    }

    /**
     * Whether these are an API token, rather than the deprecated Global API Key.
     */
    public function isToken(): bool
    {
        return '' !== $this->token;
    }

    /**
     * $text with the token or key, should it appear, replaced by [redacted].
     */
    public function redact(string $text): string
    {
        return str_replace($this->isToken() ? $this->token : $this->key, self::REDACTED, $text);
    }

    /**
     * @return array{method: string}
     */
    public function __debugInfo(): array
    {
        return ['method' => $this->describe()];
    }
}
