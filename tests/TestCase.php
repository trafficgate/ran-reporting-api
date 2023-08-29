<?php

namespace Linkshare\Api\RanReporting\Tests;

use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const PATH_TO_SAMPLE_REPORT = __DIR__ . '/data/signature-orders.csv';

    protected function createSampleReportStream(): Stream
    {
        return new Stream(fopen(self::PATH_TO_SAMPLE_REPORT, 'r'));
    }
}
