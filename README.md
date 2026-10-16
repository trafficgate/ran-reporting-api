# ran-reporting-api

API wrapper for consuming RAN reporting APIs.

This library helps you:

1. **Build** the URL for a RAN report (`Report`).
2. **Download** it with Guzzle (`Downloader`).
3. **Parse** the CSV response into arrays or objects (`Parser`).

## Requirements

- PHP 8.2 or later (tested through 8.5)
- [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) `^7.8`
- [league/csv](https://csv.thephpleague.com/) `^9.27`

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

> **Keep your token secret.** RAN takes the token as a query-string parameter, so it is part of the URL returned by `getUri()`. Use `getRedactedUri()` when you need to log the URL, and load the token from the environment or a secrets store instead of hard-coding it. `var_dump()` and `print_r()` of a `Report` hide the token.

### 2. Download the report

```php
use Linkshare\Api\RanReporting\Downloader;

$downloader = new Downloader($report);
$response   = $downloader->download();
```

`download()` returns a PSR-7 response, and `getResponse()` returns the last one. By default the body is held in memory.

#### Save to a file

Pass a destination path to write the report to disk:

```php
$downloader->download('/path/to/report.csv');

$parser = Parser::createFromPath('/path/to/report.csv');
```

The body is streamed to a temporary file in the same directory and moved into place only when the download succeeds. A failed or interrupted download never creates, replaces or truncates `report.csv`, and the temporary file is removed. The file is yours: the `Downloader` never deletes it. The directory must exist and be writable, otherwise an `InvalidArgumentException` is thrown.

#### HTTP client, proxy and TLS

The `Downloader` builds a Guzzle client with a 600 second timeout. To use your own client (a configured handler stack, or a mock in tests), pass any `GuzzleHttp\ClientInterface`:

```php
$downloader = new Downloader($report, $client);
```

The third argument is an array of default [Guzzle request options](https://docs.guzzlephp.org/en/stable/request-options.html), such as `proxy`, `verify` and `timeout`. Options passed to `download()` override them for that call:

```php
$downloader = new Downloader($report, $client, [
    'proxy'   => 'http://proxy.example.test:3128',
    'verify'  => '/path/to/ca-bundle.pem',
    'timeout' => 600,
]);

$downloader->download('/path/to/report.csv', ['verify' => false]);
```

With the client the `Downloader` builds itself, Guzzle also honours the `HTTP_PROXY`, `HTTPS_PROXY` and `NO_PROXY` environment variables. With your own client, that client's settings apply unless you override them here.

#### Retries

Transient failures are retried with exponential backoff and jitter: connection errors and timeouts, HTTP 5xx, and HTTP 429. Other failures, such as a 403 for a bad token, fail immediately. The default is 5 attempts in total, waiting up to 1 s, 2 s, 4 s and 8 s between them (capped at 60 s). Pass a `RetryPolicy` as the fourth argument to change this:

```php
use Linkshare\Api\RanReporting\RetryPolicy;

$downloader = new Downloader($report, null, [], new RetryPolicy(
    maxAttempts: 3,
    baseDelay: 2.0,
    maxDelay: 30.0
));
```

#### The token never appears in exceptions

Guzzle quotes the full URL, including the token, in exception messages, in the request attached to the exception, in the handler context and in the previous exception. When a download fails, the `Downloader` rethrows an exception of the same Guzzle class (`ClientException`, `ServerException`, `ConnectException`, `RequestException`) with the token replaced by `REDACTED` everywhere and no previous exception. `$e->getResponse()` still works. Other exceptions are rethrown unchanged unless they mention the token.

This covers exceptions thrown by `download()`. Anything that sees the URL before that, such as logging middleware on a client you pass in or Guzzle's `debug` option, still sees the real token, so log `$report->getRedactedUri()` instead.

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

A leading UTF-8 byte order mark is handled for you. Parsing follows RFC 4180: a quote inside a quoted field is written as two quotes, a backslash is an ordinary character, quoted fields may contain line breaks, and blank lines are skipped.

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
    $transaction->originalString(); // the source text of the whole record, without line ending
}
```

All values are returned as strings, exactly as they appear in the CSV. A row with fewer than 12 columns (an error page returned with a 200 status, for example) throws an `InvalidArgumentException` that says how many columns it found. The message never contains the row, which may hold member data.

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

The tests include integration tests that download from PHP's built-in web server on `127.0.0.1` (`tests/Integration/`), so they need no network access. They use a generated sample report (`tests/data/signature-orders.csv`) that contains fake data only. It can be regenerated with `php scripts/fake_report_generator.php`.

See the [CHANGELOG](CHANGELOG.md) for release notes.

## License

Released under the [MIT License](LICENSE).
