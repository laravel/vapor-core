# Release Notes

## [Unreleased](https://github.com/laravel/vapor-core/compare/v2.43.4...2.0)

## [v2.43.4](https://github.com/laravel/vapor-core/compare/v2.43.3...v2.43.4) - 2026-04-22

* [2.x] Fix test compatibility across Laravel 10-13 and PHPUnit 10-12 by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/vapor-core/pull/201
* Skip VaporServiceProvider registration on Laravel Cloud by [@kieranbrown](https://github.com/kieranbrown) in https://github.com/laravel/vapor-core/pull/202

## [v2.43.3](https://github.com/laravel/vapor-core/compare/v2.43.2...v2.43.3) - 2026-03-09

* [2.0] Catch PDOException when MySQL server has gone away during SET SESSION wait_timeout reset by [@rizalpahlevii](https://github.com/rizalpahlevii) in https://github.com/laravel/vapor-core/pull/200

## [v2.43.2](https://github.com/laravel/vapor-core/compare/v2.43.1...v2.43.2) - 2026-02-21

* Laravel 13.x Compatibility by [@laravel-shift](https://github.com/laravel-shift) in https://github.com/laravel/vapor-core/pull/199

## [v2.43.1](https://github.com/laravel/vapor-core/compare/v2.43.0...v2.43.1) - 2026-01-20

* fix: `curl_close()` is deprecated by [@mortenhauberg](https://github.com/mortenhauberg) in https://github.com/laravel/vapor-core/pull/198

## [v2.43.0](https://github.com/laravel/vapor-core/compare/v2.42.0...v2.43.0) - 2026-01-08

* Add dynamic cli handler factory. by [@Sfonxs](https://github.com/Sfonxs) in https://github.com/laravel/vapor-core/pull/197

## [v2.42.0](https://github.com/laravel/vapor-core/compare/v2.41.0...v2.42.0) - 2025-12-19

* Expose AWS Lambda execution context for Vapor logging by [@sahil7194](https://github.com/sahil7194) in https://github.com/laravel/vapor-core/pull/196

## [v2.41.0](https://github.com/laravel/vapor-core/compare/v2.40.0...v2.41.0) - 2025-09-10

* fix: Allow custom Lambda events from SQS by [@mathiasgrimm](https://github.com/mathiasgrimm) in https://github.com/laravel/vapor-core/pull/194

## [v2.40.0](https://github.com/laravel/vapor-core/compare/v2.39.0...v2.40.0) - 2025-08-04

## [v2.39.0](https://github.com/laravel/vapor-core/compare/v2.38.2...v2.39.0) - 2025-07-10

* Add API Gateway request timestamp to server variables by [@mortenhauberg](https://github.com/mortenhauberg) in https://github.com/laravel/vapor-core/pull/192

## [v2.38.2](https://github.com/laravel/vapor-core/compare/v2.38.1...v2.38.2) - 2025-07-04

## [v2.38.1](https://github.com/laravel/vapor-core/compare/v2.38.0...v2.38.1) - 2025-06-27

## [v2.38.0](https://github.com/laravel/vapor-core/compare/v2.37.9...v2.38.0) - 2025-06-02

* Fix missing write permission for changelog updater by [@olivernybroe](https://github.com/olivernybroe) in https://github.com/laravel/vapor-core/pull/189
* [2.x] Fixes support for S3-Compatible Storage like Herd minio by [@pintend](https://github.com/pintend) in https://github.com/laravel/vapor-core/pull/190

## [v2.37.9](https://github.com/laravel/vapor-core/compare/v2.37.8...v2.37.9) - 2025-01-07

* Fix parsing multidimensional array from multipart by [@Vinimaks](https://github.com/Vinimaks) in https://github.com/laravel/vapor-core/pull/186

## [v2.37.8](https://github.com/laravel/vapor-core/compare/v2.37.2...v2.37.8) - 2024-12-06

* Fix Octane cookies for v2 by [@Vinimaks](https://github.com/Vinimaks) in https://github.com/laravel/vapor-core/pull/185

## [v2.37.2](https://github.com/laravel/vapor-core/compare/v2.37.1...v2.37.2) - 2024-11-06

* Add missing files in .github by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/vapor-core/pull/178
* [2.x] Reset scoped instances before running a new vapor job by [@themsaid](https://github.com/themsaid) in https://github.com/laravel/vapor-core/pull/183
* Fix tests by [@themsaid](https://github.com/themsaid) in https://github.com/laravel/vapor-core/pull/184

## [v2.37.1](https://github.com/laravel/vapor-core/compare/v2.33.2...v2.37.1) - 2024-03-26

* Use VAPOR_ENV for non-standard environment option by [@joelvh](https://github.com/joelvh) in https://github.com/laravel/vapor-core/pull/176

## [v2.33.2](https://github.com/laravel/cashier/compare/v2.33.1...v2.33.2) - 2023-10-03

* [2.x] Adds helpers to determine when an app is running on Vapor  by @joedixon in https://github.com/laravel/vapor-core/pull/164
