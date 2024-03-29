<?php

namespace Linkshare\Api\RanReporting;

use BadMethodCallException;
use InvalidArgumentException;

class Report
{
    public const BASE_URI = 'https://ran-reporting.rakutenmarketing.com/{language}/reports/{reportName}/filters';

    public const LANGUAGE_EN = 'en';
    public const LANGUAGE_JA = 'ja';

    public const INCLUDE_SUMMARY_YES = 'Y';
    public const INCLUDE_SUMMARY_NO  = 'N';

    public const NETWORK_US    = 1;
    public const NETWORK_JAPAN = 11;

    public const TIMEZONE_GMT = 'GMT';

    public const DATE_TYPE_TRANSACTION = 'transaction';
    public const DATE_TYPE_PROCESS     = 'process';

    public const VALID_PROPERTY_NAMES = [
        'language',
        'reportName',
        'startDate',
        'endDate',
        'includeSummary',
        'network',
        'timezone',
        'dateType',
        'token',
    ];

    protected $language;
    protected $reportName;
    protected $startDate;
    protected $endDate;
    protected $includeSummary = self::INCLUDE_SUMMARY_NO;
    protected $network;
    protected $timezone;
    protected $dateType;
    protected $token;

    public function __construct(array $props = [])
    {
        foreach (self::VALID_PROPERTY_NAMES as $name) {
            if (isset($props[$name])) {
                $method = 'set' . ucfirst($name);

                $this->{$method}($props[$name]);
            }
        }
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setLanguage(string $value)
    {
        $this->language = $value;

        return $this;
    }

    public function getReportName()
    {
        return $this->reportName;
    }

    public function setReportName(string $value)
    {
        $this->reportName = $value;

        return $this;
    }

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function setStartDate(string $value)
    {
        $this->startDate = $value;

        return $this;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function setEndDate(string $value)
    {
        $this->endDate = $value;

        return $this;
    }

    public function getIncludeSummary()
    {
        return $this->includeSummary;
    }

    public function setIncludeSummary(string $value)
    {
        $validValues = [
            self::INCLUDE_SUMMARY_YES,
            self::INCLUDE_SUMMARY_NO,
        ];

        if (! in_array($value, $validValues, true)) {
            throw new InvalidArgumentException(
                $value . ' is not a valid value for includeSummary.'
            );
        }

        $this->includeSummary = $value;

        return $this;
    }

    public function getNetwork()
    {
        return $this->network;
    }

    public function setNetwork(int $value)
    {
        $this->network = $value;

        return $this;
    }

    public function getTimezone()
    {
        return $this->timezone;
    }

    public function setTimezone(string $value)
    {
        $this->timezone = $value;

        return $this;
    }

    public function getDateType()
    {
        return $this->dateType;
    }

    public function setDateType(string $value)
    {
        $validValues = [
            self::DATE_TYPE_TRANSACTION,
            self::DATE_TYPE_PROCESS,
        ];

        if (! in_array($value, $validValues, true)) {
            throw new InvalidArgumentException(
                $value . ' is not a valid value for dateType.'
            );
        }

        $this->dateType = $value;

        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken(string $value)
    {
        $this->token = $value;

        return $this;
    }

    public function getUri()
    {
        foreach (self::VALID_PROPERTY_NAMES as $name) {
            if (! isset($this->{$name})) {
                throw new BadMethodCallException($name . ' is not set.');
            }
        }

        $uri = str_replace(
            ['{language}', '{reportName}'],
            [$this->language, $this->reportName],
            self::BASE_URI
        );

        $queryString = http_build_query([
            'start_date'      => $this->startDate,
            'end_date'        => $this->endDate,
            'include_summary' => $this->includeSummary,
            'network'         => $this->network,
            'tz'              => $this->timezone,
            'date_type'       => $this->dateType,
            'token'           => $this->token,
        ]);

        return $uri . '?' . $queryString;
    }
}
