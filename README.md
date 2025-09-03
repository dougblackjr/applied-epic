# Applied Epic REST PHP Client

A pragmatic, PSR-friendly PHP client for the **Applied Epic SDK REST API**.

> Works with PHP 8.1+ and uses PSR-18 style HTTP via Guzzle, PSR-7 via nyholm/psr7, and `php-http/discovery` for factory discovery.

## Installation

```bash
composer require triplenerdscore/applied-epic-rest-php
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Tns\Epic\Epic;

$epic = Epic::make(
    baseUri: 'https://YOUR_SERVER/SDK_Service',
    authKey: 'YOUR_AUTH_KEY',
    database: 'YOUR_DB',
    accept: 'application/json' // or 'application/xml'
);

// 1) Persons: POST /api/person_search
$people = $epic->persons->search([
    'first_name' => 'John',
    'last_name'  => 'Smith',
]);

// 2) Lookups: GET /api/lookup_search
$lookupTypes = $epic->lookups->all();

// 3) Lookup values: POST /api/lookup_search/{type}
$states = $epic->lookups->values('state', ['includeInactive' => false]);

// 4) Policies: POST /api/policy_search
$policies = $epic->policies->search([
    'insured_name' => 'Acme Corp',
]);

// 5) Quotes: GET /api/option_sets/{applicationType}/{applicationVersion}
$autoSets = $epic->quotes->optionSets('Auto', '1.0');

// 6) Logins: PATCH /api/logins (partial update)
$epic->logins->patch([
    'user_id' => 123,
    'is_locked' => false
]);
```

## Design

- **Transport:** PSR-18 style with Guzzle under the hood.
- **Headers:** `AuthenticationKey` and `DatabaseName` are automatically added to every request. Content is JSON by default.
- **Retries:** 409/5xx get exponential backoff retries (configurable).
- **Extensible:** Resources are cleanly separated in `src/Resources`.

## Environment variables (optional)

Set these to simplify integration tests or bootstrapping:

- `EPIC_BASE_URI`
- `EPIC_AUTH_KEY`
- `EPIC_DATABASE`
- `EPIC_ACCEPT` (default: `application/json`)

## License

MIT © 2025 tripleNERDscore
