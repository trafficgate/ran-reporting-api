<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\InvalidArgumentException as GuzzleInvalidArgumentException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
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

    public function testRebuildsEveryKindOfRequestException(): void
    {
        $request  = new Request('GET', 'https://x.test/r?token=' . urlencode(self::TOKEN));
        $response = new Response(500);

        foreach ([
            new ServerException('msg ' . self::TOKEN, $request, $response),
            new BadResponseException('msg ' . self::TOKEN, $request, $response),
            new RequestException('msg ' . self::TOKEN, $request),
        ] as $original) {
            $redacted = $this->redactor->redact($original);

            $this->assertInstanceOf(RequestException::class, $redacted);
            $this->assertSame($original::class, $redacted::class);
            $this->assertSame('msg REDACTED', $redacted->getMessage());
            $this->assertSame(
                $original->getResponse()?->getStatusCode(),
                $redacted->getResponse()?->getStatusCode()
            );
            $this->assertSame('token=REDACTED', $redacted->getRequest()->getUri()->getQuery());
        }
    }

    public function testKeepsTheStatusAndProtocolOfTheResponse(): void
    {
        $original = new ClientException(
            'x',
            new Request('GET', 'https://x.test'),
            new Response(418, ['X-Other' => 'kept'], 'short and stout', '1.0', 'Teapot')
        );

        $response = $this->redactRequestException($original)->getResponse();

        $this->assertNotNull($response);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('Teapot', $response->getReasonPhrase());
        $this->assertSame('1.0', $response->getProtocolVersion());
        $this->assertSame('kept', $response->getHeaderLine('X-Other'));
        $this->assertSame('short and stout', (string) $response->getBody());
    }

    public function testRedactsTheTokenFromResponseBodyAndHeaders(): void
    {
        $body     = '{"error":"bad token ' . self::TOKEN . '"}';
        $original = new ClientException(
            'x',
            new Request('GET', 'https://x.test'),
            new Response(401, [
                'Location'       => ['https://x.test/r?token=' . urlencode(self::TOKEN)],
                'Content-Length' => (string) strlen($body),
            ], $body)
        );

        $response = $this->redactRequestException($original)->getResponse();

        $this->assertNotNull($response);

        $this->assertSame('{"error":"bad token REDACTED"}', (string) $response->getBody());
        $this->assertSame('https://x.test/r?token=REDACTED', $response->getHeaderLine('Location'));
        $this->assertSame(
            (string) strlen((string) $response->getBody()),
            $response->getHeaderLine('Content-Length')
        );
    }

    public function testRedactedResponseDoesNotShareTheOriginalStream(): void
    {
        $original = new Response(500, [], 'body ' . self::TOKEN);
        $redacted = $this->redactor->redactResponse($original);

        $this->assertSame('body ' . self::TOKEN, (string) $original->getBody());
        $this->assertSame('body REDACTED', (string) $redacted->getBody());
    }

    public function testLeavesUnrelatedExceptionsAlone(): void
    {
        $exception = new InvalidArgumentException('bad option');

        $this->assertSame($exception, $this->redactor->redact($exception));
    }

    public function testLeavesGuzzleExceptionsWithoutRequestsAloneWhenTheyDoNotMentionTheToken(): void
    {
        $exception = new GuzzleInvalidArgumentException('Invalid option');

        $this->assertSame($exception, $this->redactor->redact($exception));
    }

    public function testReplacesGuzzleExceptionsWithoutRequestsThatMentionTheToken(): void
    {
        $redacted = $this->redactor->redact(
            new GuzzleInvalidArgumentException('Invalid ' . self::TOKEN)
        );

        $this->assertInstanceOf(RuntimeException::class, $redacted);
        $this->assertSame('Invalid REDACTED', $redacted->getMessage());
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

    protected function redactRequestException(RequestException $original): RequestException
    {
        $redacted = $this->redactor->redact($original);

        $this->assertInstanceOf(RequestException::class, $redacted);

        return $redacted;
    }
}
