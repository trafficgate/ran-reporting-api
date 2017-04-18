<?php

namespace Linkshare\Api\RanReporting;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Middleware;

class Downloader
{
    const DEFAULT_SINK_PREFIX            = 'report-';
    const DEFAULT_RETRY_ATTEMPTS         = 1;
    const DEFAULT_RETRY_DELAY_MULTIPLIER = 500; //ms

    /**
     * @var Report
     */
    protected $report;

    /**
     * @var string
     */
    protected $sinkPath = null;

    /**
     * @var Psr\Http\Message\ResponseInterface
     */
    protected $response;

    public function __construct(
        Report $report,
        string $sinkDir = null,
        string $sinkPrefix = self::DEFAULT_SINK_PREFIX
    ) {
        $this->report = $report;

        if (isset($sinkDir)) {
            $this->sinkPath = tempnam($sinkDir, $sinkPrefix);
        }
    }

    public function __destruct()
    {
        if (isset($this->sinkPath)) {
            unlink($this->sinkPath);
        }
    }

    public function download(array $options = [])
    {
        $defaultOptions = [
            'sink'                   => $this->sinkPath,
            'retry_attempts'         => self::DEFAULT_RETRY_ATTEMPTS,
            'retry_delay_multiplier' => self::DEFAULT_RETRY_DELAY_MULTIPLIER,
        ];
        $options = array_merge($defaultOptions, $options);

        $retryAttempts        = $options['retry_attempts'];
        $retryDelayMultiplier = $options['retry_delay_multiplier'];
        
        // retry_attempts and retry_delay_multiplier are not GuzzleHttp options,
        // so unset them to avoid any conflict
        unset($options['retry_attempts']);
        unset($options['retry_delay_multiplier']);

        $decider = function ($retries, $request, $response, $exception) use ($retryAttempts) {
            if ($retries >= $retryAttempts) {
                return false;
            }

            if (isset($exception) && $exception instanceof RequestException) {
                return true;
            }

            if (isset($response) && $response->getStatusCode() != '200') {
                return true;
            }

            return false;
        };

        $delay = function ($retries) use ($retryDelayMultiplier) {
            return $retries * $retryDelayMultiplier;
        };

        $handler            = isset($options['handler']) ? $options['handler'] : \GuzzleHttp\choose_handler();
        $retry              = Middleware::retry($decider, $delay);
        $options['handler'] = $retry($handler);
        $client             = new Client($options);
        $this->response     = $client->get($this->report->getUri());

        return $this->response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getSinkPath()
    {
        return $this->sinkPath;
    }
}
