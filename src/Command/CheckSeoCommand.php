<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Command;

use Lbonnet\OnPageSeoBundle\Crawler\CrawlerInterface;
use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

#[AsCommand(
    name: 'on-page-seo:check',
    description: 'Crawls a website and audits on-page SEO signals (titles, meta descriptions, headings, image alt attributes).',
)]
final class CheckSeoCommand extends Command
{
    use LockableTrait;

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

        if (!$this->lock()) {
            $io->warning('The "on-page-seo:check" command is already running in another process. Skipping.');

            return Command::SUCCESS;
        }

        try {
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
            $io->newLine();

            $progressBar = null;

            if (!$io->isVerbose()) {
                ProgressBar::setPlaceholderFormatterDefinition(
                    'truncated_url',
                    static fn(ProgressBar $bar): string => self::truncate((string)$bar->getMessage())
                );

                $progressBar = $io->createProgressBar();
                $progressBar->setFormat(' %current% pages audited [%elapsed%] <fg=cyan>%truncated_url%</>');
                $progressBar->setMessage('Starting...');
                $progressBar->start();
            }

            $progressCallback = static function (string $currentUrl, int $totalChecked, int $issuesCount) use (
                $io,
                $progressBar
            ): void {
                if ($io->isVerbose()) {
                    $status = $issuesCount > 0
                        ? sprintf('<fg=yellow>[%d ISSUE(S)]</>', $issuesCount)
                        : '<fg=green>[OK]</>';
                    $io->text(sprintf('%s (%d) %s', $status, $totalChecked, $currentUrl));
                } elseif ($progressBar !== null) {
                    $progressBar->setMessage($currentUrl);
                    $progressBar->advance();
                }
            };

            $report = $this->crawler->crawl(
                startUrl: $startUrl,
                maxDepth: $maxDepth,
                excludePatterns: $excludePatterns,
                progressCallback: $progressCallback,
            );

            if ($progressBar !== null) {
                $progressBar->finish();
                $io->newLine(2);
            } else {
                $io->newLine();
            }

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

            self::renderIssuesReport($io, $report);

            return Command::FAILURE;
        } finally {
            $this->release();
        }
    }

    private static function renderIssuesReport(SymfonyStyle $io, SeoAuditReport $report): void
    {
        $io->section(sprintf('SEO Issues Found (%d)', $report->getIssuesCount()));

        $table = $io->createTable();
        $table->setHeaders(['Page', 'Issue', 'Message']);

        $table->setStyle('box');

        $issueWidth = 24;
        $borderOverhead = 15;
        $available = max(45, (new Terminal())->getWidth() - $issueWidth - $borderOverhead);

        $pageWidth = (int)round($available * 0.4);
        $messageWidth = (int)round($available * 0.6);

        $table->setColumnMaxWidth(0, $pageWidth);
        $table->setColumnMaxWidth(1, $issueWidth);
        $table->setColumnMaxWidth(2, $messageWidth);

        foreach ($report->pages as $page) {
            foreach ($page->issues as $issue) {
                $table->addRow([
                    sprintf('<href=%s>%s</>', $page->url, self::truncate($page->url, $pageWidth)),
                    sprintf('<fg=yellow>%s</>', $issue->type->value),
                    self::truncate($issue->message, $messageWidth),
                ]);
            }
        }

        $table->render();
        $io->newLine();

        $io->error(
            sprintf(
                'Found %d issue(s) across %d page(s) (Duration: %.2fs).',
                $report->getIssuesCount(),
                $report->totalChecked,
                $report->totalDuration
            )
        );
    }

    private static function truncate(string $text, int $maxLength = 60): string
    {
        return mb_strlen($text, 'UTF-8') > $maxLength
            ? mb_substr($text, 0, $maxLength - 3, 'UTF-8').'...'
            : $text;
    }
}
