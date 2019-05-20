<?php

namespace Linkshare\Api\RanReporting;

use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    const PATH_TO_SAMPLE_REPORT = __DIR__.'/data/signature-orders.csv';

    protected function createSampleReportStream()
    {
        return new Stream(fopen(self::PATH_TO_SAMPLE_REPORT, 'r'));
    }
}
