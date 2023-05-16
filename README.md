# quickbooks-api

- Zero Dependencies Quickbooks API
- License: MIT license
- These files are Not officially supported by [Quickbooks.com.](https://quickbooks.intuit.com/)
- Questions regarding this software should be directed to daniel.boorn@gmail.com.

How to Install
---------------

Install the `deboorn/quickbooks-api` package

```php
require_once 'src/quickbooks/api.php'
```

Why?
---------------

Keeping things simple. Zero dependencies equals very small package and dead simple.

Example of Usage
---------------

```php

$opts = [
    'client_id'     => 'my-app-client-id',
    'client_secret' => 'my-app-client-secret',
    'token'         => null,
    'realmId'       => null,
];

$qb = QuickBooks\API::forge($opts['client_id'], $opts['client_secret'], $opts['token'], $opts['realmId'], QuickBooks\API::ENV_SANDBOX);

$authCallbackUrl = 'http://localhost/tests/quickbooks/auth';

// redirect for auth code
$state = 'my-state';
$qb->redirectAuthorization($url, $state);

// get token from auth return (on auth callback url endpoint)
$token = $qb->getTokenByCode($code, $authCallbackUrl);

// set token (on future invoking after token saved)
$qb->setToken($token);

// refresh token (obtain new token using refresh token)
$qb->refreshToken();

// set realm id (set realm id)
$ab->setRealmId($realmId);

// get quickbooks endpoint (see api class)
$r = $qb->get(string $endpoint, array $params = null);
var_dump($r);

// post quickbooks endpoint (see api class)
$r = $qb->post(array $data, string $endpoint);
var_dump($r);




```
