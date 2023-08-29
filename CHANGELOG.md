# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Github Actions workflows.

### Changed

- Minimum supported versions for dependencies.
    - guzzlehttp/guzzle (`~6.0` => `^6.1.1|^7.0`)
    - league/csv (`~8.0` => `^9.4`)
- Minimum supported versions for development dependencies.
    - fakerphp/faker (`^1.6` => `^1.9`)
        - Replaces abandoned `fzaninotto/faker`
    - friendsofphp/php-cs-fixer (`^1.12` => `^3.0`)
    - phpunit/phpunit (`~5.5` => `^8.0`)
    - psy/psysh (`~0.7` => `^0.11.20`)

### Removed

- Support for PHP < 7.2.
- Dependency on `laravel/support`.
- Travis CI workflow.

## [1.0.1] - 2017-09-01

- Please fill me out.

## [1.0.0] - 2016-10-03

- Please fill me out.

[Unreleased]: https://github.com/trafficgate/ran-reporting-api/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/trafficgate/ran-reporting-api/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/trafficgate/ran-reporting-api/releases/tag/v1.0.0
