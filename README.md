# Applied API REST PHP Client

A pragmatic, PSR-friendly PHP client for the **modern Applied API** hosted at
`api.myappliedproducts.com`.

> **v2 is a breaking rewrite.** Versions `1.x` targeted Applied's *legacy Epic
> SDK* (`EpicSDK.svc`, the `AuthenticationKey`/`DatabaseName` headers, and
> `POST /api/*_search`). The modern Applied API is a different product:
> OAuth2 authentication, per-product microservices, and HAL+JSON responses.
> If you are upgrading from `1.x`, see [CHANGELOG.md](CHANGELOG.md) — there is
> no compatibility shim.

Works with PHP 8.2+. Transport is PSR-18; PSR-17 factories and the HTTP client
are located through `php-http/discovery`.

## Installation

```bash
composer require triplenerdscore/applied-epic-rest-php:^2.0
```

## How the modern Applied API works

- **Auth** — OAuth2 *client-credentials*. A Consumer Key + Secret are exchanged
  for a bearer token; every request carries `Authorization: Bearer <token>`.
- **Microservices** — each API is its own service under the host, e.g.
  `/epic/policy/v2`, `/epic/account/v1`, `/epic/opportunity/v1`,
  `/epic/attachment/v2`, `/workflow-management/v1`.
- **Paging** — `?limit=<n>&offset=<n>`.
- **Responses** — `application/hal+json`, shaped
  `{ "_embedded": { "<plural>": [...] }, "_links": {...}, "total": n }`.

This client hides all of that: it acquires and caches the token, prefixes the
right service path, and walks pagination for you.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Tns\Epic\AppliedEpic;

// 'production' or 'mock' — or pass a Tns\Epic\Environment for a custom host.
$epic = AppliedEpic::make(
    consumerKey: getenv('APPLIED_CONSUMER_KEY'),
    consumerSecret: getenv('APPLIED_CONSUMER_SECRET'),
    environment: 'production',
);

// list() paginates transparently — iterate the whole collection.
// `limit` is the page size; every page is fetched behind the scenes.
foreach ($epic->policies->list(['limit' => 50]) as $policy) {
    echo $policy['policyNumber'], ' — ', $policy['description'], PHP_EOL;
}

// Fetch one record by its opaque id.
$account = $epic->accounts->get('497f6eca-6276-4993-bfeb-53cbbbba6f08');

// Inline related resources with ?embed=
$policy = $epic->policies->get($policyId, ['embed' => 'client']);

// Download an attachment's file as a PSR-7 stream.
$pdf = $epic->attachments->download($attachmentId);
file_put_contents('claim.pdf', (string) $pdf);
```

Records are returned as plain associative arrays decoded straight from
HAL+JSON — map them onto your own models however you like.

## Resources

| Property               | Service base path         | Operations |
|------------------------|---------------------------|------------|
| `$epic->accounts`      | `/epic/account/v1`        | `list()`, `get()` |
| `$epic->policies`      | `/epic/policy/v2`         | `list()`, `get()` |
| `$epic->opportunities` | `/epic/opportunity/v1`    | `list()`, `get()` |
| `$epic->activities`    | `/workflow-management/v1` | `get()`; `list(['client' => $id])` — activities are listed per client |
| `$epic->attachments`   | `/epic/attachment/v2`     | `get()`, `download()` |

Every `list()` accepts the filter query parameters documented in the OpenAPI
specs under [`spec/`](spec/). Array-valued filters are comma-joined and
booleans are sent as `true`/`false`, matching the Applied query conventions.

## Token caching

Tokens are cached in memory for the life of the process. Pass a PSR-16 cache to
share them across processes (and avoid a token exchange on every request):

```php
$epic = AppliedEpic::make($key, $secret,
    environment: 'production',
    cache: $psr16Cache,        // any Psr\SimpleCache\CacheInterface
);
```

The cached token is refreshed automatically a little before it expires, and a
`401` from any endpoint forces a one-shot refresh-and-retry.

## Environments

| `environment` | API host                                 | Token endpoint |
|---------------|------------------------------------------|----------------|
| `production`  | `https://api.myappliedproducts.com`      | `https://api.appliedsystems.com/v1/auth/connect/token` |
| `mock`        | `https://api.mock.myappliedproducts.com` | `https://api.mock.myappliedproducts.com/v1/auth/connect/token` |

## OAuth scopes

`AppliedEpic::make()` requests the read scope of every bundled resource by
default (`AppliedEpic::DEFAULT_SCOPES`). Override with the `scopes:` argument
to request only what your integration needs.

## Advanced wiring

`AppliedEpic::make()` is a convenience factory. The pieces are public and can be
assembled directly — useful for tests or custom transports:

```php
use Tns\Epic\{Client, OAuthTokenProvider, Environment};

$env = Environment::mock();
$tokens = new OAuthTokenProvider($http, $reqFactory, $streamFactory,
    $env->tokenUrl, $key, $secret, scopes: ['epic/policies:read']);
$client = new Client($http, $reqFactory, $streamFactory, $tokens, $env->apiBaseUri);

$epic = new AppliedEpic($client);
```

Inject a mock PSR-18 client via `make(..., http: $mock)` to exercise the client
without live calls — see the test suite for examples.

## Testing

```bash
composer install
composer test
```

The suite runs entirely against HAL fixtures (`tests/fixtures/`) via a mock
PSR-18 handler — no network access required.

## License

MIT © tripleNERDscore
