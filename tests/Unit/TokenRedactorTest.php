<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use Linkshare\Api\RanReporting\Tests\TestCase;
use Linkshare\Api\RanReporting\TokenRedactor;
use RuntimeException;
use stdClass;

class TokenRedactorTest extends TestCase
{
    public const TOKEN = 'abc/123+xyz';

    protected TokenRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new TokenRedactor(self::TOKEN);
    }

    public function testRedactsTheRawAndEncodedToken(): void
    {
        $text = 'raw ' . self::TOKEN
            . ' urlencoded ' . urlencode(self::TOKEN)
            . ' rawurlencoded ' . rawurlencode(self::TOKEN);

        $redacted = $this->redactor->redactString($text);

        $this->assertStringNotContainsString('abc', $redacted);
        $this->assertSame(
            'raw REDACTED urlencoded REDACTED rawurlencoded REDACTED',
            $redacted
        );
    }

    public function testRedactsATokenParameterEvenIfTheValueIsUnknown(): void
    {
        $this->assertSame(
            'GET https://x.test/r?a=1&token=REDACTED&b=2 failed',
            $this->redactor->redactString('GET https://x.test/r?a=1&token=other&b=2 failed')
        );
    }

    public function testRedactsAFirstQueryParameter(): void
    {
        $uri = $this->redactor->redactUri(new Uri('https://x.test/r?token=' . urlencode(self::TOKEN) . '&a=1'));

        $this->assertSame('token=REDACTED&a=1', $uri->getQuery());
    }

    public function testRedactRequestKeepsMethodAndHost(): void
    {
        $request = $this->redactor->redactRequest(
            new Request('POST', 'https://x.test/r?a=1&token=' . urlencode(self::TOKEN))
        );

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('x.test', $request->getUri()->getHost());
        $this->assertSame('a=1&token=REDACTED', $request->getUri()->getQuery());
    }

    public function testRedactContextWalksArraysAndDropsObjects(): void
    {
        $context = $this->redactor->redactContext([
            'url'    => 'https://x.test/r?token=' . self::TOKEN,
            'errno'  => 28,
            'nested' => ['error' => 'failed ' . self::TOKEN],
            'handle' => new stdClass(),
        ]);

        $this->assertSame([
            'url'    => 'https://x.test/r?token=REDACTED',
            'errno'  => 28,
            'nested' => ['error' => 'failed REDACTED'],
        ], $context);
    }

    public function testRebuildsGuzzleExceptionsAsTheSameClass(): void
    {
        $request  = new Request('GET', 'https://x.test/r?token=' . urlencode(self::TOKEN));
        $response = new Response(403);
        $previous = new RuntimeException('inner ' . self::TOKEN);

        foreach ([
            new ClientException('msg ' . self::TOKEN, $request, $response, $previous),
            new TooManyRedirectsException('msg ' . self::TOKEN, $request, $response, $previous),
            new ConnectException('msg ' . self::TOKEN, $request, $previous, ['url' => (string) $request->getUri()]),
        ] as $original) {
            $redacted = $this->redactor->redact($original);

            $this->assertInstanceOf($original::class, $redacted);
            $this->assertNotSame($original, $redacted);
            $this->assertNull($redacted->getPrevious());
            $this->assertSame('msg REDACTED', $redacted->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, (string) $redacted);
            $this->assertStringNotContainsString(urlencode(self::TOKEN), (string) $redacted->getRequest()->getUri());
        }
    }

    public function testKeepsTheResponseOnRebuiltExceptions(): void
    {
        $response = new Response(403);
        $original = new ClientException('x', new Request('GET', 'https://x.test'), $response);

        $this->assertSame($response, $this->redactor->redact($original)->getResponse());
    }

    public function testLeavesUnrelatedExceptionsAlone(): void
    {
        $exception = new InvalidArgumentException('bad option');

        $this->assertSame($exception, $this->redactor->redact($exception));
    }

    public function testReplacesNonGuzzleExceptionsThatMentionTheToken(): void
    {
        $redacted = $this->redactor->redact(
            new InvalidArgumentException('failed for ?token=' . self::TOKEN, 7)
        );

        $this->assertInstanceOf(RuntimeException::class, $redacted);
        $this->assertSame('failed for ?token=REDACTED', $redacted->getMessage());
        $this->assertSame(7, $redacted->getCode());
    }

    public function testEmptyTokenOnlyUsesTheParameterPattern(): void
    {
        $redactor = new TokenRedactor('');

        $this->assertSame('plain text', $redactor->redactString('plain text'));
        $this->assertSame('?token=REDACTED', $redactor->redactString('?token=abc'));
    }
}
