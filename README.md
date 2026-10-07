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

<!--expanded-->
## Fitting the client into PHP applications

PHP applications come in many shapes: a WordPress plugin, a Laravel service, a Symfony command, a plain script on a shared host. The client is one class with no framework assumptions, so it fits all of them. What differs is where you keep the key, where you cache answers and how you run slow work.

In WordPress, call the client from a scheduled event instead of during page rendering. Page views should never wait for a network call. Store the profile in post meta or a transient, and read from there when you render. In Laravel, bind the client in a service provider and put the key in the environment file, then dispatch a queued job for each URL. In Symfony, register the client as a service with the key injected from the container parameters.

## Caching

Audience profiles change slowly, so caching is the most useful optimization. Use whatever cache your framework provides. Key entries on the normalized URL, set a lifetime of a week or two for editorial pages, and keep the vocabulary version next to each stored answer. When the version changes, mark old entries stale so a refresh can happen gradually.

```php
function audience(Client $client, string $url): array
{
    $key = 'aud_' . md5(strtolower(trim($url)));
    $hit = apcu_fetch($key, $found);
    if ($found) {
        return $hit;
    }
    $page = $client->segment($url);
    apcu_store($key, $page, 14 * 86400);
    return $page;
}
```

On shared hosting without APCu, a small table in your database does the same job.

## Long lists and time limits

PHP requests have time limits, and long lists will hit them. Do not loop over thousands of URLs inside a web request. Push each URL to a queue and let workers handle them. If you must run a batch from a command line script, write progress to a file as you go, so a crash does not lose the work already paid for. Re-running the script should skip every URL that already has an answer.

The service handles about 270 URLs a minute at 50 parallel threads. PHP workers are usually single threaded, so run a handful in parallel and watch your credit balance.

## Handling errors well

The client throws `CookielessAudiencesException`, and `getCode()` returns the API status. Treat the codes differently.

- **400, 401 and 407** mean the request or the key is wrong. Fix the code or the configuration.
- **403** means the key is inactive or credits are used up. Alert a person.
- **410** means there was not enough readable content. Record it and move on.
- **411** means the page could not be fetched. Try once more later.
- **500** means a general error. Retry with backoff, then report it.

Log the `body` property of the exception, because it holds the full response and speeds up any support conversation.

## Security notes

Never print the API key in a page or a log. Keep it out of version control. If you use a plugin system that exposes settings to administrators, store the key encrypted and show only the last few characters. Remember that the service does not need cookies or personal data from your visitors, so you should not send any. Pass page URLs, never user identifiers.

## Reading the answers in templates

The structured response is an associative array. In a template you can write small helpers that turn codes into labels and hide blocks without evidence:

```php
function interest_labels(array $page): array
{
    return \CookielessAudiences\Client::labelsFor($page);
}

function is_confident(?array $block): bool
{
    return $block !== null && in_array($block['confidence'] ?? '', ['medium', 'high'], true);
}
```

Show confident blocks as plain statements and weak ones as hints. Visitors and colleagues both learn to trust a page that is honest about how sure it is.

## Where the data helps

Publishers use the profile to describe their own readers to advertisers without exposing a single user record. Agencies use it to shortlist sites for a persona. Data teams join it onto a warehouse table. The [guide to university career centers and resume parsing](https://www.resumereaderapi.com/use-cases/university-career-centers.php) shows a neighbouring problem from the same company, where uploaded documents become structured records, and the [TAM mapping use case](https://www.acquisitionuniverse.com/use-cases/tam-mapping-for-industries.php) shows how a census of companies in an industry is built and counted.

For the audience side, the [guide to website audience demographics](https://www.cookielessaudiences.com/features/website-audience-demographics.php) explains the fields in detail, including how age brackets, gender skew and income bands are expressed.

## Compatibility and installation notes

The package needs PHP 7.4 or later with the cURL and JSON extensions. It has no other dependencies and works with Composer autoloading under the `CookielessAudiences` namespace. If your host blocks outbound connections, ask them to allow HTTPS to the service domain. The client sets a descriptive user agent string that includes the package version.

<!--extra-->
## Practical notes for PHP teams

A few habits save trouble. Set a time limit that matches the work: a web request should never wait for dozens of calls, while a command line script can run for hours. Store every answer with the date and the vocabulary version, so that you can explain a figure months later. Keep the key out of version control and out of logs. Log the status of each failure, because the status tells you what to do next: 401 and 407 mean fix the request, 403 means check the account, 410 means skip the page and 411 means try again later.

When you show audience data to people, show confidence as well. A planner who sees a plain label next to a high confidence band acts on it. A planner who sees the same label next to a low band treats it as a hint. That small difference in presentation prevents most of the misuse of audience data, and it costs one extra column in a table.

Think about the future of the page you are analysing, too. Pages change, so an answer is a snapshot. Decide how old an answer may be before it must be refreshed, and write that decision in the code as a constant with a comment. A constant with a comment is documentation that cannot drift.

Finally, test with fakes. The client accepts a callable that stands in for the network, so your tests can feed it any response and check how your code reacts, including every error status. Write one test per status code and keep them in your suite. They take seconds to write and they are the reason that failures in production look like something you have already seen.

Keep a small command line script in the project that segments one URL and prints the whole response. When someone asks why a page was labelled a certain way, you can answer in a minute, and that shortens every conversation about data quality.

<!--further-->
## Further reading

The [PHP manual](https://www.php.net/) documents the cURL and JSON functions used by the client, and [Composer](https://getcomposer.org/) documents autoloading, versions and the `require` command used in the installation steps.

## FAQ

**Composer vendor name?** `cookielessaudiences`.

**Is the HTTP code reliable?** No, read the `status` in the body. The client does it for you.

**License?** MIT. info@alpha-quantum.com
