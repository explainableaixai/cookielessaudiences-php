# cookielessaudiences/cookielessaudiences (PHP)

PHP client for Cookieless Audiences. Pass a URL, receive the audience of that page: age, income, interests, purchase intent, B2B context and personas. The service reads the page, not the visitor, so there are no cookies and no personal data.

Needs PHP 7.4 or later with cURL and JSON.

## Install

```bash
composer require cookielessaudiences/cookielessaudiences
```

## Example

```php
<?php
require 'vendor/autoload.php';

use CookielessAudiences\Client;

$client = new Client(getenv('COOKIELESS_KEY'));
$page = $client->segment('https://example.com/blog');

echo $page['audience_type'], "\n";
print_r(Client::labelsFor($page));
```

## Class reference

| Method | Purpose |
|---|---|
| `new Client($key, $timeout = 120)` | create a client |
| `segment($url, $structured = true)` | audience profile |
| `categorize($url, $confidence = true, $rootFallback = false)` | IAB categories |
| `categorizeText($text)` | IAB categories for text |
| `vocabularies()` | all allowed values, no key |
| `Client::labelsFor($result)` | readable code names |

Failures throw `CookielessAudiences\CookielessAudiencesException`. `getCode()` is the API status and `$e->body` holds the raw response.

## WordPress and Laravel notes

In WordPress, call the client from a cron hook, not on page render, and store the profile in a transient. In Laravel, bind the client in a service provider and keep the key in `.env`:

```php
$this->app->singleton(Client::class, fn () => new Client(config('services.cookieless.key')));
```

## Example: enrich a CRM export

```php
$rows = array_map('str_getcsv', file('accounts.csv'));
$header = array_shift($rows);
$out = fopen('accounts_enriched.csv', 'w');
fputcsv($out, array_merge($header, ['audience_type', 'top_interest']));

foreach ($rows as $row) {
    $site = $row[array_search('website', $header, true)];
    try {
        $page = $client->segment($site);
        $labels = Client::labelsFor($page);
        fputcsv($out, array_merge($row, [$page['audience_type'] ?? '', $labels[0] ?? '']));
    } catch (\CookielessAudiences\CookielessAudiencesException $e) {
        fputcsv($out, array_merge($row, ['error ' . $e->getCode(), '']));
    }
}
```

Run it in the background for long files and respect your plan's credit balance.

## Market research angle

The same call answers a research question such as which of these 200 sites skew toward directors in data roles. The [market research use case](https://www.cookielessaudiences.com/use-cases/market-research.php) covers that workflow.

## Testing without the network

The third constructor argument is a callable transport. Return a canned JSON string and your tests never leave the machine.

```php
$client = new Client('k', 5, fn ($method, $url, $form) => '{"status":200,"audience_type":"b2b"}');
```

## FAQ

**Composer vendor name?** `cookielessaudiences`.

**Is the HTTP code reliable?** No, read the `status` in the body. The client does it for you.

**License?** MIT. info@alpha-quantum.com
