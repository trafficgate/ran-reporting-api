<?php

namespace Linkshare\Api\RanReporting;

use BadMethodCallException;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Report
{
    const BASE_URI = 'https://ran-reporting.rakutenmarketing.com/{language}/reports/{reportName}/filters';

    const LANGUAGE_EN = 'en';
    const LANGUAGE_JA = 'ja';

    const INCLUDE_SUMMARY_YES = 'Y';
    const INCLUDE_SUMMARY_NO  = 'N';

    const NETWORK_US    = 1;
    const NETWORK_JAPAN = 11;

    const TIMEZONE_GMT = 'GMT';

    const DATE_TYPE_TRANSACTION = 'transaction';
    const DATE_TYPE_PROCESS     = 'process';

    const VALID_PROPERTY_NAMES = [
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

    /**
     * @var string
     */
    protected $language;

    /**
     * @var string
     */
    protected $reportName;

    /**
     * @var string
     */
    protected $startDate;

    /**
     * @var string
     */
    protected $endDate;

    /**
     * @var string
     */
    protected $includeSummary = self::INCLUDE_SUMMARY_NO;

    /**
     * @var int
     */
    protected $network;

    /**
     * @var string
     */
    protected $timezone;

    /**
     * @var string
     */
    protected $dateType;

    /**
     * @var string
     */
    protected $token;

    public function __construct(array $props = [])
    {
        foreach (self::VALID_PROPERTY_NAMES as $name) {
            if (isset($props[$name])) {
                $method = 'set'.Str::studly($name);

                $this->{$method}($props[$name]);
            }
        }
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $value): Report
    {
        $this->language = $value;

        return $this;
    }

    public function getReportName(): string
    {
        return $this->reportName;
    }

    public function setReportName(string $value): Report
    {
        $this->reportName = $value;

        return $this;
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function setStartDate(string $value): Report
    {
        $this->startDate = $value;

        return $this;
    }

    public function getEndDate(): string
    {
        return $this->endDate;
    }

    public function setEndDate(string $value): Report
    {
        $this->endDate = $value;

        return $this;
    }

    public function getIncludeSummary(): string
    {
        return $this->includeSummary;
    }

    public function setIncludeSummary(string $value): Report
    {
        $validValues = [
            self::INCLUDE_SUMMARY_YES,
            self::INCLUDE_SUMMARY_NO,
        ];

        if (! in_array($value, $validValues, true)) {
            throw new InvalidArgumentException(
                $value.' is not a valid value for includeSummary.'
            );
        }

        $this->includeSummary = $value;

        return $this;
    }

    public function getNetwork(): int
    {
        return $this->network;
    }

    public function setNetwork(int $value): Report
    {
        $this->network = $value;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $value): Report
    {
        $this->timezone = $value;

        return $this;
    }

    public function getDateType(): string
    {
        return $this->dateType;
    }

    public function setDateType(string $value): Report
    {
        $validValues = [
            self::DATE_TYPE_TRANSACTION,
            self::DATE_TYPE_PROCESS,
        ];

        if (! in_array($value, $validValues, true)) {
            throw new InvalidArgumentException(
                $value.' is not a valid value for dateType.'
            );
        }

        $this->dateType = $value;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $value): Report
    {
        $this->token = $value;

        return $this;
    }

    public function getUri(): string
    {
        foreach (self::VALID_PROPERTY_NAMES as $name) {
            if (! isset($this->$name)) {
                throw new BadMethodCallException($name.' is not set.');
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

        return $uri.'?'.$queryString;
    }
}
