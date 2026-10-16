<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Removes an API token from exceptions before they leave the library.
 *
 * RAN takes the token in the query string, and Guzzle quotes the full URL in
 * exception messages, in the request attached to the exception, in the handler
 * context, and in the previous exception. Editing the message is not enough, so
 * redact() builds a new exception of the same class instead.
 */
final class TokenRedactor
{
    public function __construct(private readonly string $token) {}

    public function redactString(string $value): string
    {
        if ($this->token !== '') {
            $value = str_replace(
                [$this->token, rawurlencode($this->token), urlencode($this->token)],
                Report::REDACTED_TOKEN,
                $value
            );
        }

        return preg_replace(
            '/([?&]token=)[^&#\s`\'"]*/i',
            '$1' . Report::REDACTED_TOKEN,
            $value
        ) ?? $value;
    }

    public function redactUri(UriInterface $uri): UriInterface
    {
        // The prefix lets the same pattern match a token at the start of the query.
        $query = substr($this->redactString('?' . $uri->getQuery()), 1);

        return $uri->withQuery($query);
    }

    public function redactRequest(RequestInterface $request): RequestInterface
    {
        return $request->withUri($this->redactUri($request->getUri()), true);
    }

    /**
     * Strings are redacted and arrays are walked. Anything else (curl handles,
     * objects) is dropped because it cannot be inspected safely.
     */
    public function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $redacted[$key] = $this->redactString($value);
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactContext($value);
            } elseif (is_scalar($value) || $value === null) {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Returns an exception that is safe to throw to the caller.
     *
     * Guzzle request and connection exceptions are rebuilt as the same class,
     * without the previous exception. Any other exception is returned as it is,
     * unless it mentions the token, in which case it becomes a RuntimeException.
     */
    public function redact(Throwable $exception): Throwable
    {
        $message = $this->redactString($exception->getMessage());

        if ($exception instanceof GuzzleException) {
            $rebuilt = $this->rebuildGuzzleException($exception, $message);

            if ($rebuilt !== null) {
                return $rebuilt;
            }
        }

        if (! $this->mentionsToken($exception)) {
            return $exception;
        }

        return new RuntimeException($message, (int) $exception->getCode());
    }

    private function rebuildGuzzleException(
        Throwable $exception,
        string $message
    ): ?Throwable {
        if ($exception instanceof ConnectException) {
            return new ConnectException(
                $message,
                $this->redactRequest($exception->getRequest()),
                null,
                $this->redactContext($exception->getHandlerContext())
            );
        }

        if ($exception instanceof RequestException) {
            $class = match (true) {
                $exception instanceof ClientException           => ClientException::class,
                $exception instanceof ServerException           => ServerException::class,
                $exception instanceof BadResponseException      => BadResponseException::class,
                $exception instanceof TooManyRedirectsException => TooManyRedirectsException::class,
                default                                         => RequestException::class,
            };

            return new $class(
                $message,
                $this->redactRequest($exception->getRequest()),
                $exception->getResponse(),
                null,
                $this->redactContext($exception->getHandlerContext())
            );
        }

        return null;
    }

    private function mentionsToken(Throwable $exception): bool
    {
        $text = (string) $exception;

        return ($this->token !== '' && str_contains($text, $this->token))
            || preg_match('/[?&]token=(?!' . Report::REDACTED_TOKEN . ')/i', $text) === 1;
    }
}
