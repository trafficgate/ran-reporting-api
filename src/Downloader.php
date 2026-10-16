<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class Downloader
{
    public const DEFAULT_TIMEOUT = 600;

    protected ClientInterface $client;

    protected RetryPolicy $retryPolicy;

    protected ?ResponseInterface $response = null;

    /**
     * @param Report               $report      The report to download
     * @param ClientInterface|null $client      Defaults to a Guzzle client with a 600 second timeout
     * @param array<string, mixed> $options     Default Guzzle request options (proxy, verify, timeout, ...)
     * @param RetryPolicy|null     $retryPolicy Defaults to 5 attempts with exponential backoff
     */
    public function __construct(
        protected Report $report,
        ?ClientInterface $client = null,
        protected array $options = [],
        ?RetryPolicy $retryPolicy = null
    ) {
        $this->client      = $client      ?? new Client(['timeout' => self::DEFAULT_TIMEOUT]);
        $this->retryPolicy = $retryPolicy ?? new RetryPolicy();
    }

    /**
     * Downloads the report.
     *
     * With a $destination the body is written to a temporary file next to it
     * and moved into place only if the download succeeds, so a failed or
     * interrupted download never replaces or truncates an existing file.
     * Without one, the body is held in the response.
     *
     * Exceptions never contain the report token.
     *
     * @param array<string, mixed> $options Guzzle request options, overriding the constructor defaults
     *
     * @throws InvalidArgumentException If the destination directory is not writable
     * @throws RuntimeException         If the file cannot be moved into place
     * @throws GuzzleException          If the download fails (the token is removed from it)
     */
    public function download(
        ?string $destination = null,
        array $options = []
    ): ResponseInterface {
        $options  = array_merge($this->options, $options);
        $uri      = $this->report->getUri();
        $redactor = new TokenRedactor((string) $this->report->getToken());
        $tempPath = null;

        if ($destination !== null) {
            $tempPath        = $this->createTempFile($destination);
            $options['sink'] = $tempPath;
        }

        try {
            $response = $this->send($uri, $options, $redactor);

            if ($tempPath !== null) {
                $this->moveIntoPlace($tempPath, $destination);

                $tempPath = null;
            }
        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return $this->response = $response;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws GuzzleException
     */
    protected function send(
        string $uri,
        array $options,
        TokenRedactor $redactor
    ): ResponseInterface {
        for ($attempt = 1;; ++$attempt) {
            try {
                return $this->client->request('GET', $uri, $options);
            } catch (Throwable $exception) {
                if (
                    $attempt < $this->retryPolicy->maxAttempts
                    && $this->retryPolicy->shouldRetry($exception)
                ) {
                    $this->retryPolicy->sleep($this->retryPolicy->delay($attempt));

                    continue;
                }

                throw $redactor->redact($exception);
            }
        }
    }

    protected function moveIntoPlace(string $tempPath, string $destination): void
    {
        $reason = null;

        // Report failure as our own exception, not as a PHP warning that a
        // framework error handler may turn into something less specific.
        set_error_handler(static function (int $number, string $message) use (&$reason): bool {
            $reason = $message;

            return true;
        });

        try {
            $moved = rename($tempPath, $destination);
        } finally {
            restore_error_handler();
        }

        if (! $moved) {
            throw new RuntimeException(
                'Unable to move the downloaded report to ' . $destination
                . ($reason !== null ? ': ' . $reason : '.')
            );
        }
    }

    protected function createTempFile(string $destination): string
    {
        $directory = dirname($destination);

        if (is_dir($destination)) {
            throw new InvalidArgumentException(
                $destination . ' is a directory, not a file path.'
            );
        }

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new InvalidArgumentException(
                $directory . ' is not a writable directory.'
            );
        }

        $tempPath = tempnam($directory, '.' . basename($destination) . '.');

        if ($tempPath === false) {
            throw new RuntimeException(
                'Unable to create a temporary file in ' . $directory . '.'
            );
        }

        // tempnam() creates the file as 0600; give it the mode a normal new file would get.
        chmod($tempPath, 0666 & ~umask());

        return $tempPath;
    }
}
