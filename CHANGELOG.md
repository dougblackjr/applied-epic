# Changelog

All notable changes to `triplenerdscore/applied-epic-rest-php` are documented
here. This project adheres to [Semantic Versioning](https://semver.org/).

## 2.0.0 — 2026-05-22

### Changed — BREAKING

Complete rewrite for the **modern Applied API** (`api.myappliedproducts.com`).
The `1.x` line targeted Applied's *legacy Epic SDK* — a different product —
and has been removed entirely. There is no compatibility shim; upgrading is a
deliberate migration.

- **Authentication** is now OAuth2 client-credentials. A Consumer Key + Secret
  are exchanged for a bearer token at the Applied auth endpoint, and every
  request carries `Authorization: Bearer <token>`. The legacy
  `AuthenticationKey` / `DatabaseName` headers are gone.
- **Transport** is microservice-aware. Each API has its own base path under the
  host (`/epic/policy/v2`, `/epic/account/v1`, `/epic/opportunity/v1`,
  `/epic/attachment/v2`, `/workflow-management/v1`).
- **Responses** are `application/hal+json`. `list()` walks `limit`/`offset`
  pagination transparently and yields records out of `_embedded`.
- **Minimum PHP** raised to 8.2.

### Added

- `Tns\Epic\AppliedEpic` — facade with the `AppliedEpic::make()` static
  factory (`consumerKey`, `consumerSecret`, `environment: 'production'|'mock'`).
- `Tns\Epic\OAuthTokenProvider` — client-credentials token exchange with
  in-memory and optional PSR-16 caching, an expiry leeway, and 401 refresh.
- `Tns\Epic\Client` — PSR-18 transport that injects the bearer token, exposes
  `get()`, `paginate()`, and `getStream()`, and is configurable per host.
- `Tns\Epic\Environment` — `production` / `mock` / custom connection targets.
- `Tns\Epic\Token` — bearer token value object.
- Typed resources `Accounts`, `Policies`, `Opportunities`, `Activities`,
  `Attachments`, each with `list()` / `get()`; `Attachments::download()`
  returns a PSR-7 stream.
- `Tns\Epic\Exceptions\{AppliedEpicException, AuthException, ApiException}`.
- OpenAPI 3 specs for the supported APIs under `spec/`, and HAL fixtures plus
  a mock PSR-18 handler under `tests/`.

### Removed

- The `Epic` facade and `Http\BaseClient`.
- The legacy `Lookups`, `Persons`, `Policies`, `Quotes`, and `Logins`
  resources and their `POST /api/*_search` operations.
- `Exceptions\EpicApiException` — replaced by `Exceptions\ApiException`.
- `AuthenticationKey` / `DatabaseName` header handling and the
  `EPIC_BASE_URI` / `EPIC_AUTH_KEY` / `EPIC_DATABASE` environment variables.

## 1.x

Legacy client for Applied's Epic SDK REST API (`EpicSDK.svc`). No longer
maintained.
