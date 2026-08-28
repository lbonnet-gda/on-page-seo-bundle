<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Command;

use Lbonnet\OnPageSeoBundle\Crawler\CrawlerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'on-page-seo:check',
    description: 'Crawls a website and audits on-page SEO signals (titles, meta descriptions, headings, image alt attributes).',
)]
final class CheckSeoCommand extends Command
{
    public function __construct(
        private readonly CrawlerInterface $crawler,
        private readonly ?string $defaultBaseUrl = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'url',
                InputArgument::OPTIONAL,
                'The starting URL to crawl (defaults to on_page_seo.base_url)'
            )
            ->addOption(
                'max-depth',
                'd',
                InputOption::VALUE_REQUIRED,
                'Override the maximum crawl depth'
            )
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Additional regex patterns for URLs to exclude'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $startUrl */
        $startUrl = $input->getArgument('url') ?? $this->defaultBaseUrl;

        if ($startUrl === null || trim($startUrl) === '') {
            $io->error('No URL provided. Pass an URL as argument or configure "on_page_seo.base_url".');

            return Command::INVALID;
        }

        /** @var string|null $maxDepthOption */
        $maxDepthOption = $input->getOption('max-depth');
        $maxDepth = $maxDepthOption !== null ? (int)$maxDepthOption : null;

        /** @var list<string> $excludePatterns */
        $excludePatterns = (array)$input->getOption('exclude');

        $io->title('On-Page SEO Audit');
        $io->text(sprintf('Starting crawl on: <info>%s</info>', $startUrl));

        $progressCallback = static function (string $currentUrl, int $totalChecked, int $issuesCount) use ($io): void {
            if ($io->isVerbose()) {
                $status = $issuesCount > 0 ? sprintf('<fg=yellow>[%d ISSUE(S)]</>', $issuesCount) : '<fg=green>[OK]</>';
                $io->text(sprintf('%s (%d) %s', $status, $totalChecked, $currentUrl));
            }
        };

        $report = $this->crawler->crawl(
            startUrl: $startUrl,
            maxDepth: $maxDepth,
            excludePatterns: $excludePatterns,
            progressCallback: $progressCallback,
        );

        $io->newLine();

        if (!$report->hasIssues()) {
            $io->success(
                sprintf(
                    'All clear! Audited %d page(s) in %.2fs with 0 issues.',
                    $report->totalChecked,
                    $report->totalDuration
                )
            );

            return Command::SUCCESS;
        }

        $io->section(sprintf('SEO Issues Found (%d)', $report->getIssuesCount()));

        $tableRows = [];
        foreach ($report->pages as $page) {
            foreach ($page->issues as $issue) {
                $tableRows[] = [$page->url, $issue->type->value, $issue->message];
            }
        }

        $io->table(['Page', 'Issue Type', 'Message'], $tableRows);

        $io->error(
            sprintf(
                'Found %d issue(s) across %d page(s) (Duration: %.2fs).',
                $report->getIssuesCount(),
                $report->totalChecked,
                $report->totalDuration
            )
        );

        return Command::FAILURE;
    }
}
