<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class TokenSet
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public AccessToken $accessToken,
        public TokenType $tokenType,
        public DateTimeImmutable $expiresAt,
        public ?RefreshToken $refreshToken = null,
        public array $scopes = [],
    ) {}

    public function expiresWithin(ClockInterface $clock, int $seconds): bool
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('The expiration leeway cannot be negative.');
        }

        return $this->expiresAt->getTimestamp() <= $clock->now()->getTimestamp() + $seconds;
    }
}
