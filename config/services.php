<?php

declare(strict_types=1);

use Lbonnet\CrawlerToolkit\Robots\RobotsTxtChecker;
use Lbonnet\CrawlerToolkit\Robots\RobotsTxtCheckerInterface;
use Lbonnet\OnPageSeoBundle\Auditor\PageAuditor;
use Lbonnet\OnPageSeoBundle\Command\CheckSeoCommand;
use Lbonnet\OnPageSeoBundle\Crawler\SiteCrawler;
use Lbonnet\OnPageSeoBundle\MessageHandler\CheckSeoMessageHandler;
use Lbonnet\OnPageSeoBundle\Storage\JsonFileReportStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Lbonnet\\OnPageSeoBundle\\', '../src/')
        ->exclude([
            '../src/OnPageSeoBundle.php',
            '../src/DependencyInjection/',
            '../src/Model/',
            '../src/Event/',
            '../src/Message/',
        ]);

    $services->set(PageAuditor::class)
        ->arg('$maxTitleLength', param('on_page_seo.max_title_length'))
        ->arg('$maxDescriptionLength', param('on_page_seo.max_description_length'));

    $services->set(SiteCrawler::class)
        ->arg('$httpClient', service('on_page_seo.http_client'))
        ->arg('$defaultMaxDepth', param('on_page_seo.max_depth'))
        ->arg('$defaultTimeout', param('on_page_seo.timeout'))
        ->arg('$userAgent', param('on_page_seo.user_agent'))
        ->arg('$defaultExcludePatterns', param('on_page_seo.exclude_patterns'));

    $services->set(RobotsTxtChecker::class)
        ->arg('$httpClient', service('on_page_seo.http_client'))
        ->arg('$userAgent', param('on_page_seo.user_agent'))
        ->arg('$enabled', param('on_page_seo.respect_robots_txt'));

    $services->alias(RobotsTxtCheckerInterface::class, RobotsTxtChecker::class);

    $services->set(CheckSeoCommand::class)
        ->arg('$defaultBaseUrl', param('on_page_seo.base_url'));

    $services->set(CheckSeoMessageHandler::class)
        ->arg('$defaultBaseUrl', param('on_page_seo.base_url'));

    $services->set(JsonFileReportStorage::class)
        ->arg('$storageDirectory', param('on_page_seo.storage_dir'))
        ->arg('$maxReports', param('on_page_seo.storage_max_reports'));
};
