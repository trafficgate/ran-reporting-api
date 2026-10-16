<?php

declare(strict_types=1);

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

    protected ?string $language      = null;
    protected ?string $reportName    = null;
    protected ?string $startDate     = null;
    protected ?string $endDate       = null;
    protected string $includeSummary = self::INCLUDE_SUMMARY_NO;
    protected ?int $network          = null;
    protected ?string $timezone      = null;
    protected ?string $dateType      = null;
    protected ?string $token         = null;

    public function __construct(array $props = [])
    {
        foreach (self::VALID_PROPERTY_NAMES as $name) {
            if (isset($props[$name])) {
                $method = 'set' . ucfirst($name);

                $this->{$method}($props[$name]);
            }
        }
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $value): static
    {
        $this->language = $value;

        return $this;
    }

    public function getReportName(): ?string
    {
        return $this->reportName;
    }

    public function setReportName(string $value): static
    {
        $this->reportName = $value;

        return $this;
    }

    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    public function setStartDate(string $value): static
    {
        $this->startDate = $value;

        return $this;
    }

    public function getEndDate(): ?string
    {
        return $this->endDate;
    }

    public function setEndDate(string $value): static
    {
        $this->endDate = $value;

        return $this;
    }

    public function getIncludeSummary(): string
    {
        return $this->includeSummary;
    }

    public function setIncludeSummary(string $value): static
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

    public function getNetwork(): ?int
    {
        return $this->network;
    }

    public function setNetwork(int $value): static
    {
        $this->network = $value;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(string $value): static
    {
        $this->timezone = $value;

        return $this;
    }

    public function getDateType(): ?string
    {
        return $this->dateType;
    }

    public function setDateType(string $value): static
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

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $value): static
    {
        $this->token = $value;

        return $this;
    }

    public function getUri(): string
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
