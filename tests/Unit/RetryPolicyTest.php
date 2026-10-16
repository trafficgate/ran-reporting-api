<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Linkshare\Api\RanReporting\RetryPolicy;
use Linkshare\Api\RanReporting\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

class RetryPolicyTest extends TestCase
{
    #[DataProvider('retryDecisionProvider')]
    public function testShouldRetry(Throwable $exception, bool $expected): void
    {
        $this->assertSame($expected, (new RetryPolicy())->shouldRetry($exception));
    }

    public static function retryDecisionProvider(): array
    {
        $request = new Request('GET', 'https://example.test');

        return [
            'connection error'       => [new ConnectException('timed out', $request), true],
            'HTTP 500'               => [new ServerException('', $request, new Response(500)), true],
            'HTTP 503'               => [new ServerException('', $request, new Response(503)), true],
            'HTTP 429'               => [new ClientException('', $request, new Response(429)), true],
            'HTTP 403'               => [new ClientException('', $request, new Response(403)), false],
            'HTTP 404'               => [new ClientException('', $request, new Response(404)), false],
            'request error, no body' => [new RequestException('tls', $request), false],
            'unrelated exception'    => [new RuntimeException('x'), false],
        ];
    }

    public function testDefaults(): void
    {
        $policy = new RetryPolicy();

        $this->assertSame(5, $policy->maxAttempts);
        $this->assertSame(1.0, $policy->baseDelay);
        $this->assertSame(60.0, $policy->maxDelay);
    }

    public function testDelayIsJitteredWithinAnExponentialCeiling(): void
    {
        $policy = new RetryPolicy(5, 2.0, 10.0);

        foreach ([1 => 2.0, 2 => 4.0, 3 => 8.0, 4 => 10.0, 10 => 10.0] as $attempt => $ceiling) {
            for ($i = 0; $i < 50; ++$i) {
                $delay = $policy->delay($attempt);

                $this->assertGreaterThanOrEqual(0.0, $delay);
                $this->assertLessThanOrEqual($ceiling, $delay);
            }
        }
    }

    public function testSleepUsesTheInjectedSleeper(): void
    {
        $slept  = [];
        $policy = new RetryPolicy(3, 1.0, 1.0, function (float $seconds) use (&$slept): void {
            $slept[] = $seconds;
        });

        $policy->sleep(1.5);

        $this->assertSame([1.5], $slept);
    }

    public function testRequiresAtLeastOneAttempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryPolicy(0);
    }

    public function testRejectsNegativeDelays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryPolicy(3, -1.0);
    }
}
