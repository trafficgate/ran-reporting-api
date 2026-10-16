# ran-reporting-api

API wrapper for consuming RAN reporting APIs.

This library helps you:

1. **Build** the URL for a RAN report (`Report`).
2. **Download** it with Guzzle (`Downloader`).
3. **Parse** the CSV response into arrays or objects (`Parser`).

## Requirements

- PHP 8.2 or later (tested through 8.5)
- `ext-mbstring`
- [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) `^7.8`
- [league/csv](https://csv.thephpleague.com/) `^9.15`

## Installation

```bash
composer require linkshare/ran-reporting-api
```

## Usage

### 1. Build the report URL

`SignatureOrders` is a `Report` with the report name preset to `signature-orders-report`. Pass the settings to the constructor, use the fluent setters, or mix the two.

```php
use Linkshare\Api\RanReporting\Report;
use Linkshare\Api\RanReporting\SignatureOrders;

$report = new SignatureOrders([
    'language'  => Report::LANGUAGE_EN,
    'startDate' => '2016-08-01',
    'endDate'   => '2016-08-22',
    'network'   => Report::NETWORK_US,
    'timezone'  => Report::TIMEZONE_GMT,
    'dateType'  => Report::DATE_TYPE_PROCESS,
    'token'     => getenv('RAN_REPORTING_TOKEN'),
]);

$report->setIncludeSummary(Report::INCLUDE_SUMMARY_NO);

$uri = $report->getUri();
```

`getUri()` throws a `BadMethodCallException` if any setting has not been set. `includeSummary` defaults to `N`, so you don't need to set it.

| Setting          | Setter                | Accepted values                                                         |
| ---------------- | --------------------- | ----------------------------------------------------------------------- |
| `language`       | `setLanguage()`       | `Report::LANGUAGE_EN` (`en`), `Report::LANGUAGE_JA` (`ja`)              |
| `reportName`     | `setReportName()`     | Any report name, e.g. `signature-orders-report`                         |
| `startDate`      | `setStartDate()`      | Date string, e.g. `2016-08-01`                                          |
| `endDate`        | `setEndDate()`        | Date string, e.g. `2016-08-22`                                          |
| `includeSummary` | `setIncludeSummary()` | `Report::INCLUDE_SUMMARY_YES` (`Y`), `Report::INCLUDE_SUMMARY_NO` (`N`) |
| `network`        | `setNetwork()`        | `Report::NETWORK_US` (`1`), `Report::NETWORK_JAPAN` (`11`)              |
| `timezone`       | `setTimezone()`       | e.g. `Report::TIMEZONE_GMT`                                             |
| `dateType`       | `setDateType()`       | `Report::DATE_TYPE_TRANSACTION`, `Report::DATE_TYPE_PROCESS`            |
| `token`          | `setToken()`          | Your RAN reporting API token                                            |

`setIncludeSummary()` and `setDateType()` throw an `InvalidArgumentException` for any other value.

To use a report that has no dedicated class, use `Report` directly and call `setReportName()`.

> **Keep your token secret.** The token is sent as a query-string parameter, so it appears in the URL returned by `getUri()`. Don't log or print that URL, and load the token from the environment or a secrets store instead of hard-coding it.

### 2. Download the report

```php
use Linkshare\Api\RanReporting\Downloader;

$downloader = new Downloader($report);
$response   = $downloader->download();
```

`download()` returns a PSR-7 response. It accepts an array of [Guzzle request options](https://docs.guzzlephp.org/en/stable/request-options.html), such as `timeout` or `handler`. `getResponse()` returns the last response.

By default the body is held in memory. For large reports, pass a directory and the response is streamed to a temporary file in it:

```php
$downloader = new Downloader($report, '/path/to/tmp', 'my-prefix-');
$downloader->download();

$path = $downloader->getSinkPath();
```

The file name prefix is optional and defaults to `report-`. The directory must exist and be writable, otherwise an `InvalidArgumentException` is thrown.

The temporary file is **deleted when the `Downloader` is destroyed**. Keep the `Downloader` in scope until you've finished reading `getSinkPath()`, or copy the file somewhere else first.

### 3. Parse the CSV

Create a `Parser` from a response, a string, or a file path:

```php
use Linkshare\Api\RanReporting\Parser;

$parser = Parser::createFromResponse($response);
$parser = Parser::createFromString($csvString);
$parser = Parser::createFromPath($downloader->getSinkPath());
```

`parse()` is a generator that yields one record per row and skips the header row, so large reports don't need to fit in memory. `getHeader()` returns the header row.

```php
$header = $parser->getHeader();

foreach ($parser->parse() as $record) {
    // ...
}
```

A leading UTF-8 byte order mark is handled for you.

#### Record formats

By default, each record is a plain array of the row's columns. To get something else, pass a `RecordFactory` as the last argument.

The library includes a factory for the signature orders report, which returns `SignatureOrders\Transaction` objects:

```php
use Linkshare\Api\RanReporting\SignatureOrders\TransactionFactory;

$parser = Parser::createFromResponse($response, new TransactionFactory());

foreach ($parser->parse() as $transaction) {
    $transaction->memberId();
    $transaction->mid();
    $transaction->advertiserName();
    $transaction->orderId();
    $transaction->transactionDate();
    $transaction->transactionTime();
    $transaction->sku();
    $transaction->sales();
    $transaction->numberOfItems();
    $transaction->totalCommission();
    $transaction->processDate();
    $transaction->processTime();
    $transaction->originalString(); // the raw CSV line
}
```

All values are returned as strings, exactly as they appear in the CSV.

To support another report, implement `Linkshare\Api\RanReporting\RecordFactory`. It receives the parsed row and the raw line for every record, including the header.

```php
use Linkshare\Api\RanReporting\RecordFactory;

class MyFactory implements RecordFactory
{
    public function create(array $row, string $line)
    {
        return new MyRecord($row, $line);
    }
}
```

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
```

The tests use a generated sample report (`tests/data/signature-orders.csv`) that contains fake data only. It can be regenerated with `php scripts/fake_report_generator.php`.

See the [CHANGELOG](CHANGELOG.md) for release notes.

## License

Released under the [MIT License](LICENSE).
