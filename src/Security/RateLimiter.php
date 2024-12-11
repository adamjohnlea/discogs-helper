<?php

declare(strict_types=1);

namespace DiscogsHelper\Security;

use DiscogsHelper\Exceptions\RateLimitExceededException;
use DiscogsHelper\Logger;
use DiscogsHelper\Session;

final class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes
    private const ATTEMPT_KEY_PREFIX = 'login_attempts.';
    private const TIMESTAMP_KEY_PREFIX = 'last_login_attempt.';

    public function __construct(
        private readonly string $identifier,
        private readonly ?int $maxAttempts = self::MAX_ATTEMPTS,
        private readonly ?int $lockoutDuration = self::LOCKOUT_DURATION
    ) {}

    public function check(): void
    {
        $attempts = Session::get(self::ATTEMPT_KEY_PREFIX . $this->identifier, 0);
        $lastAttempt = Session::get(self::TIMESTAMP_KEY_PREFIX . $this->identifier, 0);

        if ($attempts > $this->maxAttempts) {
            $timeRemaining = $this->lockoutDuration - (time() - $lastAttempt);

            if ($timeRemaining > 0) {
                Logger::log("Rate limit exceeded for: {$this->identifier}");
                throw new RateLimitExceededException(
                    sprintf(
                        'Too many attempts. Please try again in %d minutes.',
                        ceil($timeRemaining / 60)
                    )
                );
            }

            $this->reset();
        }
    }

    public function recordAttempt(): void
    {
        $attempts = Session::get(self::ATTEMPT_KEY_PREFIX . $this->identifier, 0);
        Session::set(self::ATTEMPT_KEY_PREFIX . $this->identifier, $attempts + 1);
        Session::set(self::TIMESTAMP_KEY_PREFIX . $this->identifier, time());
    }

    public function reset(): void
    {
        Session::remove(self::ATTEMPT_KEY_PREFIX . $this->identifier);
        Session::remove(self::TIMESTAMP_KEY_PREFIX . $this->identifier);
    }
}
