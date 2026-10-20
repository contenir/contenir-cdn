# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased] - 2026-10-21

### Added

- `PurgerInterface`, which a CMS calls to purge URLs or everything, best effort, and `NullPurger`, which purges
  nothing.
- `CacheTag`: the tags a public page carries (`c-site`, `c-sec-<section>`, `c-rec-<section>-<id>`).
- `Batch`: the URLs or tags for a purge in requests of at most 100, each named once.
- `DebouncingPurger`, which merges the purges asked for within a few seconds into one request.
- `UrlList`, which checks an editor's list of URLs, one to a line, against the site's hosts, where `*.example.com`
  stands for every subdomain.
- A Cloudflare API client (`Cloudflare\Client`) over PSR-18, signing in with an API token, or with the deprecated
  email and Global API Key while a site has no token. It never shows either secret.
- `Cloudflare\CloudflarePurger`, which logs every request with its trigger, scope, count, result and Cloudflare's
  request id, and `Cloudflare\ConnectionCheck`, which checks the credentials and reads the zone's plan without
  purging anything.
