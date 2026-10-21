# contenir/contenir-cdn

[![Continuous Integration](https://github.com/contenir/contenir-cdn/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-cdn/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-cdn/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-cdn)

Framework-free CDN purging for [Contenir](https://github.com/contenir) sites:

- `PurgerInterface`, which a CMS calls to drop pages from whatever caches the site in front of it, and `NullPurger`
  for a site with none;
- cache-tag naming (`CacheTag`), so a purge can name the pages showing a record instead of their URLs;
- batching (`Batch`), at most 100 URLs or tags to a request;
- debouncing (`DebouncingPurger`), which merges purges asked for within a few seconds into one request;
- checking an editor's list of URLs against the site's hosts (`UrlList`);
- a Cloudflare API client that signs in with an API token, a purger built on it that logs every request, and a
  connection test.

It has no framework dependency. It sends HTTP through any PSR-18 client and logs through PSR-3. The CMS uses it
through the [Cloudflare add-on](https://github.com/contenir-extension/cloudflare) (`contenir-extension/cloudflare`),
and a second CDN would be a second add-on over the same interface.

## Requirements

- PHP 8.3, 8.4 or 8.5
- A PSR-18 HTTP client and PSR-17 factories, such as Guzzle 7 or `symfony/http-client` with `nyholm/psr7`

## Purging through Cloudflare

```php
use Contenir\Cdn\Cloudflare\Client;
use Contenir\Cdn\Cloudflare\CloudflarePurger;
use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;

$credentials = Credentials::choose($token, $email, $globalKey);   // null: Cloudflare isn't set up
$client      = new Client($httpClient, $requestFactory, $streamFactory, $credentials);
$purger      = new CloudflarePurger($client, ZoneId::parse($zoneId), $logger, trigger: 'content');

$purger->purgeEverything();                         // best effort: logs a failure, never throws
$report = $purger->purgeUrls(['https://www.example.com/news/']);   // and says how it went
```

### Credentials

`Credentials::choose()` takes an API token when there is one. Without one, it falls back to the account's email and
Global API Key, which is **deprecated**: the key can do anything the account can, where a token can be limited to
purging one zone. Make a token in Cloudflare under My Profile > API Tokens, with these permissions for the site's
zone:

| Permission | Needed for |
|---|---|
| Zone · Cache Purge · Purge | purging |
| Zone · Zone · Read | the connection test, which reads the zone's plan |

Neither secret is ever shown: `var_dump()` and `print_r()` name only how the client signs in, the constructors mark
them `#[SensitiveParameter]`, and every message the client or purger writes has them taken out.

### Requests

`Client` makes the five requests purging needs, and returns Cloudflare's answer as an `ApiResponse`: whether it
succeeded, its errors, its result, and its request id (the `cf-ray` header), which Cloudflare's support asks for. A
request Cloudflare refuses is returned like any other; only one that gets no answer at all throws a
`TransportException`.

| Method | Request |
|---|---|
| `verify()` | `GET /user/tokens/verify`, or `GET /user` with the Global API Key |
| `zone($zone)` | `GET /zones/{zone}` |
| `purgeEverything($zone)` | `POST /zones/{zone}/purge_cache` with `purge_everything` |
| `purgeUrls($zone, $urls)` | the same with `files`: 1 to 100 URLs |
| `purgeTags($zone, $tags)` | the same with `tags`: 1 to 100 tags |

Since April 2025 every Cloudflare plan can purge everything, by URL, by hostname, by cache tag and by prefix
(`Zone::purgeMethods()`). Plans differ in how many purges a minute they take, and Enterprise takes 500 URLs or tags to
a request rather than 100. This library always sends at most 100.

### The log

`CloudflarePurger` logs one entry for every request it makes, at `info` when Cloudflare purged and `error` when it
refused or couldn't be reached. Each entry's context holds:

| Key | Holds |
|---|---|
| `trigger` | what started the purge, as the purger was given it: `content`, `asset` or `person`, say |
| `scope` | `everything` or `urls` |
| `count` | how many URLs; 0 for everything |
| `targets` | the URLs |
| `zone` | the zone ID |
| `result` | `purged`, `rejected` or `failed` |
| `request_id` | Cloudflare's id for the request, when it gave one |
| `status` | the HTTP status, when Cloudflare answered |
| `error` | Cloudflare's errors, or why it couldn't be reached |

The URLs are public addresses. Nothing about the site's visitors is logged.

### Testing the connection

```php
$connection = (new ConnectionCheck($client))->run($zone);

$connection->succeeded();       // the credentials work and can read the zone
$connection->zone?->plan;       // "Pro Plan"
$connection->problem;           // what to fix, in words an editor can act on
$connection->deprecatedKey;     // signed in with the Global API Key
```

The test purges nothing, so it can't prove the token may purge. The first purge does, and the log says if it may not.

## Debouncing

```php
$purger = new DebouncingPurger($cloudflare, $clock, seconds: 5);
```

The first purge waits. Those asked for in the next five seconds join it, and the next one after that sends them all
as one request. A purge of everything takes in the URLs waiting with it. Whatever still waits is sent by `flush()`,
which runs when the purger is destroyed, at the latest as the request ends.

## Cache tags

```php
CacheTag::site();                                 // c-site
CacheTag::section('library.news.articles');       // c-sec-library.news.articles
CacheTag::record('library.news.articles', 12);    // c-rec-library.news.articles-12
```

A public page carries `c-site`, `c-sec-<section>` for each section whose records it shows, listings included, and
`c-rec-<section>-<id>` for each record it shows. A save then purges its record's tag and its section's, which covers
the listing pages, and the CMS needs no URLs. Names are lower-cased, and anything but letters, digits, dots and
underscores becomes an underscore, so a hyphen only ever separates the parts.

Sending the tags is a site adapter's job. Those adapters (`contenir/contenir-cdn-laminas-mvc` and `contenir/contenir-cdn-mezzio`) come
with version 2 of the Cloudflare add-on.

## An editor's list of URLs

```php
$list = UrlList::fromText($typed, ['example.com', '*.example.com']);

$list->urls;      // the URLs to purge
$list->refused;   // each line refused, and why
```

A CDN caches each URL by its full address, so each line must be an absolute `http` or `https` URL on one of the site's
hosts. A host written `*.example.com` stands for every subdomain of `example.com`. Blank lines are skipped and repeats
kept once.

## Licence

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
