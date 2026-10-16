<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

use Closure;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use InvalidArgumentException;
use Throwable;

/**
 * Decides whether a failed download is worth retrying, and how long to wait.
 *
 * Only transient failures are retried: connection errors and timeouts, HTTP
 * 5xx and HTTP 429. Anything else (a 403 for a bad token, for example) will
 * not fix itself, so it fails immediately.
 */
final class RetryPolicy
{
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const DEFAULT_BASE_DELAY   = 1.0;
    public const DEFAULT_MAX_DELAY    = 60.0;

    private readonly Closure $sleeper;

    /**
     * @param int           $maxAttempts Total attempts, including the first one
     * @param float         $baseDelay   Seconds to wait after the first failure; doubles each time
     * @param float         $maxDelay    Upper bound for a single wait, in seconds
     * @param callable|null $sleeper     Receives the seconds to wait; defaults to usleep()
     */
    public function __construct(
        public readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        public readonly float $baseDelay = self::DEFAULT_BASE_DELAY,
        public readonly float $maxDelay = self::DEFAULT_MAX_DELAY,
        ?callable $sleeper = null
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }

        if ($baseDelay < 0 || $maxDelay < 0) {
            throw new InvalidArgumentException('Delays must not be negative.');
        }

        $this->sleeper = $sleeper !== null
            ? Closure::fromCallable($sleeper)
            : static function (float $seconds): void {
                usleep((int) round($seconds * 1_000_000));
            };
    }

    public function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectException) {
            return true;
        }

        if ($exception instanceof BadResponseException) {
            $status = $exception->getResponse()->getStatusCode();

            return $status >= 500 || $status === 429;
        }

        return false;
    }

    /**
     * Seconds to wait after the given failed attempt (1-based), using
     * exponential backoff with full jitter.
     */
    public function delay(int $failedAttempt): float
    {
        $ceiling = min(
            $this->maxDelay,
            $this->baseDelay * (2 ** max(0, $failedAttempt - 1))
        );

        return $ceiling * (mt_rand() / mt_getrandmax());
    }

    public function sleep(float $seconds): void
    {
        ($this->sleeper)($seconds);
    }
}
