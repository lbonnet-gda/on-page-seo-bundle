# OnPageSeoBundle

[![CI](https://github.com/lbonnet-gda/on-page-seo-bundle/actions/workflows/ci.yaml/badge.svg)](https://github.com/lbonnet-gda/on-page-seo-bundle/actions/workflows/ci.yaml)
[![Latest Version](https://img.shields.io/packagist/v/lbonnet/on-page-seo-bundle.svg)](https://packagist.org/packages/lbonnet/on-page-seo-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/lbonnet/on-page-seo-bundle.svg)](https://packagist.org/packages/lbonnet/on-page-seo-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A Symfony bundle to crawl a site and audit on-page SEO signals: titles, meta descriptions, headings, image alt
attributes, and duplicate titles/descriptions across pages.

Designed to run **outside the request/response cycle** — as a console command, a scheduled cron, or a CI check — so it
fits both CI pipelines and periodic audits of a live site.

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

### 3. Automated Monitoring (Symfony Scheduler)

If you use `symfony/scheduler` (Symfony 6.3+), you can schedule periodic audits in your application's
`ScheduleProvider`:

```php
namespace App\Scheduler;

use Lbonnet\OnPageSeoBundle\Message\CheckSeoMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run daily at 03:00 AM
                RecurringMessage::cron('0 3 * * *', new CheckSeoMessage())
            );
    }
}
```

### 4. Custom Notifications & Event Handling

When a crawl completes, a `CrawlCompletedEvent` is dispatched. You can listen to this event to send alerts (Slack,
Email, Discord) or perform custom actions:

```php
namespace App\EventListener;

use Lbonnet\OnPageSeoBundle\Event\CrawlCompletedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

#[AsEventListener]
final class OnPageSeoNotificationListener
{
    public function __construct(
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(CrawlCompletedEvent $event): void
    {
        $report = $event->report;

        if (!$report->hasIssues()) {
            return;
        }

        $message = sprintf(
            'Found %d SEO issue(s) on %s (audited %d page(s) in %.2fs).',
            $report->getIssuesCount(),
            $report->startUrl,
            $report->totalChecked,
            $report->totalDuration
        );

        $notification = new Notification($message, ['chat/slack', 'email']);
        $this->notifier->send($notification);
    }
}
```

> [!NOTE]
> If a `CrawlCompletedEvent` listener throws (e.g., a misconfigured notifier transport), the bundle catches and logs
> the error instead of letting it propagate — a broken notification integration won't discard an otherwise
> successful audit or prevent the JSON report from being saved.

### 5. Report Storage

By default, every completed audit automatically saves a detailed JSON snapshot in `var/on_page_seo/`:

```json
{
    "startUrl": "https://example.com",
    "createdAt": "2026-08-14T14:15:00+02:00",
    "totalChecked": 12,
    "totalDuration": 1.84,
    "issuesCount": 2,
    "pages": [
        {
            "url": "https://example.com/about",
            "title": "About us",
            "metaDescription": null,
            "issues": [
                {
                    "type": "missing_description",
                    "message": "The page has no meta description."
                }
            ]
        }
    ]
}
```

To disable automatic file storage, set `storage_dir: null` in your bundle configuration.

Reports are rotated per crawled URL: only the `storage_max_reports` most recent (30 by default) are kept for a given
start URL, so a daily scheduled audit doesn't grow the storage directory forever. Set it to `0` to keep every report.

## Notes

### SSRF protection

The crawler follows every link it finds on the pages it visits — including links planted by whoever controls the content
being audited. If you point it at untrusted or third-party content, a malicious page could contain a link to
`http://169.254.169.254/...` (cloud instance metadata), `http://localhost:6379` (an internal service), or any other
address on your private network, and the bundle would otherwise dutifully request it from the machine running the audit.

To prevent this, requests are made through Symfony's [
`NoPrivateNetworkHttpClient`](https://symfony.com/doc/current/http_client.html#ssrf-server-side-request-forgery-handling),
which blocks requests resolving (including via DNS) to private, loopback, or link-local IP ranges. This is **on by
default** and applies to every HTTP request the bundle makes.

Set `allow_private_network: true` only if you intentionally want to audit an internal network (e.g., a staging site
reachable solely from behind a VPN) — and only when the content being crawled is fully trusted, since this also re-opens
the SSRF exposure described above.

### Being a polite crawler

Two settings help keep an audit well-behaved:

- **`request_delay_ms`** (`200` by default) enforces a minimum delay between consecutive requests to a host. The host
  you're auditing is unthrottled against itself by default — it's the one site you actually control and want audited
  quickly — so this setting only ever slows down requests to *other* hosts the crawl happens to reach.
- **`respect_robots_txt`** (on by default) fetches the audited site's `robots.txt` once and stops the crawler from
  following or auditing further pages under a disallowed path. It doesn't affect the URL you explicitly pass as the
  audit's starting point. If that same `robots.txt` publishes a `Crawl-delay` for our user agent, it overrides
  `request_delay_ms` for the audited host specifically — the site owner's explicit request takes precedence over the
  "unthrottled against itself" default.

### Query strings and duplicate content false positives

Pages are audited as-is: `/blog?p=1` and `/blog?p=2` are treated as two distinct pages, since a query string often
identifies genuinely different content rather than noise. On sites where some parameters are pure noise instead
(pagination, sorting, tracking like `utm_*`), this can trigger duplicate-title/duplicate-description false positives
between what is really the same page under different query strings — use `exclude_patterns` to filter those out
explicitly, e.g. `'#[?&]utm_#'` or `'#\?page=#'`.

## Security

To report a vulnerability, please don't open a public issue — see [SECURITY.md](SECURITY.md) for how to report it
privately.

## License

MIT — see [LICENSE](LICENSE).
