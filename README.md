# OnPageSeoBundle

A Symfony bundle to crawl a site and audit on-page SEO signals: titles, meta descriptions, headings, and image alt
attributes.

Designed to run **outside the request/response cycle** — as a console command, a scheduled cron, or a CI check — so it
fits both CI pipelines and periodic audits of a live site.

Broken link detection is out of scope here — pair this bundle
with [lbonnet/link-checker-bundle](https://github.com/lbonnet-gda/link-checker-bundle) for that.

## Requirements

- PHP >= 8.1
- Symfony 6.4, 7.4, or 8.1

## Installation

```bash
composer require lbonnet/on-page-seo-bundle
```

If you don't use Symfony Flex, enable the bundle manually in `config/bundles.php`:

```php
return [
    // ...
    Lbonnet\OnPageSeoBundle\OnPageSeoBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/on_page_seo.yaml`:

```yaml
on_page_seo:
    base_url: 'https://example.com'   # default site to crawl
    max_depth: 3 # crawl depth from the start URL
    timeout: 10 # per-request timeout (seconds)
    user_agent: 'Mozilla/5.0 (compatible; OnPageSeoBundle/1.0; +https://github.com/lbonnet-gda/on-page-seo-bundle)'
    max_title_length: 60 # flag titles longer than this
    max_description_length: 155 # flag meta-descriptions longer than this
    exclude_patterns: # URLs matching these regexes are skipped
        - '#/admin#'
        - '#\.pdf$#'
    storage_dir: '%kernel.project_dir%/var/on_page_seo' # JSON reports directory; set to null/empty to disable
    storage_max_reports: 30 # oldest reports are deleted past this count per crawled URL (0 = keep forever)
    allow_private_network: false # set true only to intentionally audit an internal network (SSRF risk otherwise)
    request_delay_ms: 200 # minimum delay between requests to the same host (0 = no throttling); the audited host is exempt
    respect_robots_txt: true # skip pages disallowed by the crawled site's robots.txt and honor its Crawl-delay
```

## Usage

### 1. Console Command (CLI & CI)

```bash
php bin/console on-page-seo:check [url] [--max-depth=N] [--exclude=PATTERN ...]
```

The `url` argument is optional if `on_page_seo.base_url` is configured. The command exits with a non-zero status code
when SEO issues are found, so it can be used as a CI check.

### 2. Asynchronous Execution (Messenger)

The bundle provides a `CheckSeoMessage` and its handler to offload the audit to an asynchronous worker queue:

```php
use Lbonnet\OnPageSeoBundle\Message\CheckSeoMessage;use Symfony\Component\Messenger\MessageBusInterface;

// In a controller, command or custom service
public function triggerAudit(MessageBusInterface $bus): void
{
    // Uses default configuration values
    $bus->dispatch(new CheckSeoMessage());

    // Or with custom parameters
    $bus->dispatch(new CheckSeoMessage(
        startUrl: 'https://example.com/blog',
        maxDepth: 2,
        excludePatterns: ['#/preview#'],
    ));
}
```

## License

MIT — see [LICENSE](LICENSE).
