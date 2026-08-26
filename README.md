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
    max_title_length: 60 # flag titles longer than this
    max_description_length: 160 # flag meta-descriptions longer than this
    exclude_patterns: # URLs matching these regexes are skipped
        - '#/admin#'
        - '#\.pdf$#'
```

## Usage

> Command coming in a later step.

```bash
php bin/console on-page-seo:check
```

## Roadmap

- [x] Bundle skeleton and configuration
- [x] Page metadata extraction (title, meta description, headings, image alt)
- [ ] Site crawler orchestration
- [ ] Per-page audit rules (missing/too long title and description, missing H1, images without alt)
- [ ] Duplicate title/description detection across pages
- [ ] Console command
- [ ] Result persistence
- [ ] Tests & CI matrix

## License

MIT — see [LICENSE](LICENSE).
