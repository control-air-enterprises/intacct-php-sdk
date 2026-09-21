<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\OAuth;

use ControlAir\Intacct\Exceptions\InvalidArgument;

final readonly class PkcePair
{
    public string $challenge;

    public function __construct(
        #[\SensitiveParameter]
        private string $verifier,
    ) {
        $length = strlen($this->verifier);

        if ($length < 43 || $length > 128 || preg_match('/^[A-Za-z0-9\-._~]+$/', $this->verifier) !== 1) {
            throw new InvalidArgument('A PKCE verifier must contain 43-128 RFC 7636 unreserved characters.');
        }

        $this->challenge = self::base64UrlEncode(hash('sha256', $this->verifier, true));
    }

    public static function generate(): self
    {
        return new self(self::base64UrlEncode(random_bytes(64)));
    }

    public function verifier(): string
    {
        return $this->verifier;
    }

    /** @return array{verifier: string, challenge: string} */
    public function __debugInfo(): array
    {
        return [
            'verifier' => '[redacted]',
            'challenge' => $this->challenge,
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
