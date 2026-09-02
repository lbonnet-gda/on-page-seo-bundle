<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle;

use Lbonnet\CrawlerToolkit\Http\ThrottledHttpClient;
use Lbonnet\OnPageSeoBundle\Crawler\SiteCrawler;
use Lbonnet\OnPageSeoBundle\Storage\JsonFileReportStorage;
use Lbonnet\OnPageSeoBundle\Storage\ReportStorageInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OnPageSeoBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // @formatter:off
        $definition->rootNode()
            ->children()
                ->scalarNode('base_url')
                    ->defaultNull()
                    ->info('Default base URL to crawl when none is passed to the command.')
                ->end()
                ->integerNode('max_depth')
                    ->defaultValue(3)
                    ->min(0)
                    ->info('Maximum crawl depth from the starting URL.')
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(10)
                    ->min(1)
                    ->info('Per-request timeout in seconds when fetching a page.')
                ->end()
                ->scalarNode('user_agent')
                    ->defaultValue(SiteCrawler::DEFAULT_USER_AGENT)
                    ->info('User-Agent header sent when fetching a page. Identify your crawler honestly; do not spoof a browser UA to bypass bot protection.')
                ->end()
                ->integerNode('max_title_length')
                    ->defaultValue(60)
                    ->min(1)
                    ->info('Titles longer than this (in characters) are flagged as too long.')
                ->end()
                ->integerNode('max_description_length')
                    ->defaultValue(155)
                    ->min(1)
                    ->info('Meta descriptions longer than this (in characters) are flagged as too long.')
                ->end()
                ->arrayNode('exclude_patterns')
                    ->scalarPrototype()->end()
                    ->info('Regular expression patterns for URLs to skip.')
                ->end()
                ->scalarNode('storage_dir')
                    ->defaultValue('%kernel.project_dir%/var/on_page_seo')
                    ->info('Directory where SEO audit reports in JSON will be stored. Set to empty or null to disable.')
                ->end()
                ->integerNode('storage_max_reports')
                    ->defaultValue(30)
                    ->min(0)
                    ->info('Maximum number of stored reports to keep per crawled URL; the oldest are deleted past that. Set to 0 to keep every report forever.')
                ->end()
                ->booleanNode('allow_private_network')
                    ->defaultFalse()
                    ->info('Allow requests to URLs resolving to private/loopback/link-local IP ranges (e.g. 127.0.0.1, 10.0.0.0/8, cloud metadata endpoints). The crawler follows links found on the pages it visits, so leaving this disabled (default) prevents SSRF if it ever crawls untrusted or third-party content. Enable only to intentionally audit an internal network.')
                ->end()
                ->integerNode('request_delay_ms')
                    ->defaultValue(200)
                    ->min(0)
                    ->info('Minimum delay, in milliseconds, enforced between consecutive requests to the same host. The host you\'re crawling (the "url" argument/"base_url") is always exempt, so this only slows down requests to other hosts. Set to 0 to disable throttling entirely.')
                ->end()
                ->booleanNode('respect_robots_txt')
                    ->defaultTrue()
                    ->info('Fetch and honor the crawled site\'s robots.txt: matching Disallow rules stop the crawler from following/auditing further internal pages under that path. Does not apply to the URL you explicitly start the crawl from.')
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    /**
     * @param array{
     *     base_url: string|null,
     *     max_depth: int,
     *     timeout: int,
     *     user_agent: string,
     *     max_title_length: int,
     *     max_description_length: int,
     *     exclude_patterns: list<string>,
     *     storage_dir: string|null,
     *     storage_max_reports: int,
     *     allow_private_network: bool,
     *     request_delay_ms: int,
     *     respect_robots_txt: bool,
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $container->parameters()
            ->set('on_page_seo.base_url', $config['base_url'])
            ->set('on_page_seo.max_depth', $config['max_depth'])
            ->set('on_page_seo.timeout', $config['timeout'])
            ->set('on_page_seo.user_agent', $config['user_agent'])
            ->set('on_page_seo.max_title_length', $config['max_title_length'])
            ->set('on_page_seo.max_description_length', $config['max_description_length'])
            ->set('on_page_seo.exclude_patterns', $config['exclude_patterns'])
            ->set('on_page_seo.storage_dir', $config['storage_dir'])
            ->set('on_page_seo.storage_max_reports', $config['storage_max_reports'])
            ->set('on_page_seo.allow_private_network', $config['allow_private_network'])
            ->set('on_page_seo.request_delay_ms', $config['request_delay_ms'])
            ->set('on_page_seo.respect_robots_txt', $config['respect_robots_txt']);

        $privateNetworkGuardId = 'on_page_seo.http_client.private_network_guard';

        if ($config['allow_private_network']) {
            $builder->setAlias($privateNetworkGuardId, HttpClientInterface::class);
        } else {
            $builder->register($privateNetworkGuardId, NoPrivateNetworkHttpClient::class)
                ->setArguments([new Reference(HttpClientInterface::class)]);
        }

        $builder->register('on_page_seo.http_client', ThrottledHttpClient::class)
            ->setArguments([new Reference($privateNetworkGuardId), $config['request_delay_ms']]);

        if ($config['storage_dir'] === null || $config['storage_dir'] === '') {
            $builder->removeDefinition(JsonFileReportStorage::class);
            $builder->removeAlias(ReportStorageInterface::class);
        }
    }
}
