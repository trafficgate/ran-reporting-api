# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

A small PHP library (`linkshare/ran-reporting-api`, namespace `Linkshare\Api\RanReporting`) for downloading and parsing CSV reports from the Rakuten Advertising (RAN) reporting API. It requires PHP ^8.2, so avoid syntax newer than 8.2 (for example, no typed class constants, which need 8.3). Every file uses `declare(strict_types=1)`.

## Commands

```bash
composer install
vendor/bin/phpunit                                   # all tests
vendor/bin/phpunit tests/Unit/ParserTest.php         # one file
vendor/bin/phpunit --filter testName                 # one test
composer analyse                                     # PHPStan: src at max, tests at level 8, no baseline
composer test-coverage                               # needs pcov or Xdebug (CI uses pcov)
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php           # fix style
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff   # check only
php scripts/fake_report_generator.php                # regenerate tests/data/signature-orders.csv
```

CI runs PHPUnit 11 on PHP 8.2–8.5 with both `prefer-lowest` and `prefer-stable`, so dependency changes must work at the lowest allowed versions (Guzzle 7.8, league/csv 9.27). A separate workflow runs php-cs-fixer on every push and auto-commits fixes, another runs PHPStan, and a `coverage` job in `run-tests.yml` reports pcov coverage in the job summary.

PHPStan must stay clean without a baseline or ignore comments. Fix the types instead, for example with `array<...>` docblocks and `@throws`. The one exception is a test that passes a wrong type on purpose to prove the `TypeError`, which carries a targeted `@phpstan-ignore` with the reason.

`composer.lock` and `phpunit.xml` are gitignored. Local PHPUnit overrides go in `phpunit.xml`.

## Architecture

The flow is three independent steps: `Report` builds the URL, `Downloader` fetches it, and `Parser` turns the CSV into records.

- **`Report`** is a fluent builder for the report URL. Its constructor takes an array keyed by `VALID_PROPERTY_NAMES` and calls the matching `setX()` methods. `getUri()` throws `BadMethodCallException` unless **every** property in `VALID_PROPERTY_NAMES` is set, including `reportName` and `token`. Report-specific subclasses (e.g. `SignatureOrders`) work by presetting `$reportName`. A new report type should follow that pattern.
- **`Downloader`** takes an injectable `GuzzleHttp\ClientInterface` and default Guzzle request options (proxy, verify, timeout). `download($destination)` retries transient failures per `RetryPolicy`, writes to a temp file beside the destination, and `rename()`s it into place only on success. It never deletes the file afterwards.
- **Token handling is the critical invariant.** RAN takes the token in the query string and Guzzle quotes the whole URL in exception messages, requests, handler context and previous exceptions. `Downloader::send()` is the single outermost boundary: every exception leaving it goes through `TokenRedactor::redact()`, which rebuilds the Guzzle exception (same class, no previous). Do not catch-and-rethrow raw Guzzle exceptions elsewhere, do not put `getUri()` in messages or logs (use `getRedactedUri()`), and keep `testExceptionsNeverContainTheToken` passing.
- **`Parser`** is built through the static `createFromResponse/String/Path` factories (the constructor is protected). `parse()` is a generator that skips the header row. `getHeader()` returns just the header.
- **`RecordFactory`** is the extension point for record shape. The `Parser` calls `create(array $row, string $line)` for every record. `ArrayFactory` is the default and returns the raw row. Per-report factories, such as `SignatureOrders\TransactionFactory`, return value objects and keep the original raw line (`originalString()`).

### Parser detail worth knowing

For each record the `Parser` pairs two views of the same source: the row parsed by `League\Csv\Reader` and the record's raw text from `rawRecords()`. The raw text is passed to the `RecordFactory`. A record is not always one line (quoted fields may contain newlines) and blank lines are skipped, so `rawRecords()` joins physical lines until the quotes balance and skips blank ones, in the same way League does. That only holds under RFC 4180 quoting, which is why `createCsvReader()` calls `setEscape('')`. If you change how either side splits records, keep them aligned and keep the alignment tests in `ParserTest` passing. The BOM is removed from both sides.

`SignatureOrders\Transaction` maps CSV columns by numeric index constants, so the column order in the report defines the class. The sample fixture (`tests/data/signature-orders.csv`) is generated with fake data by `scripts/fake_report_generator.php`, which writes a UTF-8 BOM and CRLF line endings to mimic the real API. `ParserTest` asserts exactly 1532 records, so regenerating the fixture with a different row count breaks the tests.

`tests/Integration/DownloaderHttpTest.php` runs `Downloader` over real HTTP against `php -S` with `tests/Integration/router.php` (a different scenario per URL prefix), so redaction is checked against real cURL errors and real responses. Run it with the normal suite. It skips itself if a local server cannot be started. Don't start `php -S` with `PHP_CLI_SERVER_WORKERS`: the workers survive `proc_terminate()` and keep PHPUnit's output pipe open, which hangs `phpunit | tail`.

## Conventions

- Follow `CHANGELOG.md` (Keep a Changelog format) and add entries under `[Unreleased]`.
- `composer.json` declares a `branch-alias` for `dev-master` (`3.0.x-dev`). Update it when cutting a new major or minor version.
