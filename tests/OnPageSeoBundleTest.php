<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests;

use Lbonnet\CrawlerToolkit\Http\ThrottledHttpClient;
use Lbonnet\CrawlerToolkit\Robots\RobotsTxtChecker;
use Lbonnet\CrawlerToolkit\Robots\RobotsTxtCheckerInterface;
use Lbonnet\OnPageSeoBundle\Auditor\PageAuditor;
use Lbonnet\OnPageSeoBundle\Command\CheckSeoCommand;
use Lbonnet\OnPageSeoBundle\Crawler\CrawlerInterface;
use Lbonnet\OnPageSeoBundle\Crawler\SiteCrawler;
use Lbonnet\OnPageSeoBundle\OnPageSeoBundle;
use Lbonnet\OnPageSeoBundle\Storage\JsonFileReportStorage;
use Lbonnet\OnPageSeoBundle\Storage\ReportStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OnPageSeoBundleTest extends TestCase
{
    public function testDefaultConfigurationAndParameters(): void
    {
        $container = $this->createContainer();
        $bundle = new OnPageSeoBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertNotNull($extension);

        $extension->load([], $container);

        $this->assertNull($container->getParameter('on_page_seo.base_url'));
        $this->assertSame(3, $container->getParameter('on_page_seo.max_depth'));
        $this->assertSame(10, $container->getParameter('on_page_seo.timeout'));
        $this->assertSame(SiteCrawler::DEFAULT_USER_AGENT, $container->getParameter('on_page_seo.user_agent'));
        $this->assertSame(60, $container->getParameter('on_page_seo.max_title_length'));
        $this->assertSame(155, $container->getParameter('on_page_seo.max_description_length'));
        $this->assertSame([], $container->getParameter('on_page_seo.exclude_patterns'));
        $this->assertSame(
            '%kernel.project_dir%/var/on_page_seo',
            $container->getParameter('on_page_seo.storage_dir')
        );
        $this->assertSame(30, $container->getParameter('on_page_seo.storage_max_reports'));
        $this->assertFalse($container->getParameter('on_page_seo.allow_private_network'));
        $this->assertSame(200, $container->getParameter('on_page_seo.request_delay_ms'));
        $this->assertTrue($container->getParameter('on_page_seo.respect_robots_txt'));

        $this->assertTrue($container->hasDefinition('on_page_seo.http_client.private_network_guard'));
        $this->assertSame(
            NoPrivateNetworkHttpClient::class,
            $container->getDefinition('on_page_seo.http_client.private_network_guard')->getClass()
        );

        $this->assertTrue($container->hasDefinition('on_page_seo.http_client'));
        $httpClientDefinition = $container->getDefinition('on_page_seo.http_client');
        $this->assertSame(ThrottledHttpClient::class, $httpClientDefinition->getClass());
        $this->assertSame(
            'on_page_seo.http_client.private_network_guard',
            (string)$httpClientDefinition->getArgument(0)
        );
        $this->assertSame(200, $httpClientDefinition->getArgument(1));
    }

    public function testAllowPrivateNetworkDisablesSsrfProtection(): void
    {
        $container = $this->createContainer();
        $bundle = new OnPageSeoBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertNotNull($extension);

        $extension->load(
            ['on_page_seo' => ['allow_private_network' => true, 'request_delay_ms' => 250]],
            $container
        );

        $this->assertTrue($container->getParameter('on_page_seo.allow_private_network'));
        $this->assertTrue($container->hasAlias('on_page_seo.http_client.private_network_guard'));
        $this->assertSame(
            HttpClientInterface::class,
            (string)$container->getAlias('on_page_seo.http_client.private_network_guard')
        );

        $httpClientDefinition = $container->getDefinition('on_page_seo.http_client');
        $this->assertSame(ThrottledHttpClient::class, $httpClientDefinition->getClass());
        $this->assertSame(250, $httpClientDefinition->getArgument(1));
    }

    public function testCustomConfigurationAndServiceRegistration(): void
    {
        $container = $this->createContainer();
        $bundle = new OnPageSeoBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertNotNull($extension);

        $customConfig = [
            'on_page_seo' => [
                'base_url' => 'https://example.com',
                'max_depth' => 5,
                'timeout' => 20,
                'user_agent' => 'CustomBot/2.0',
                'storage_dir' => '/custom/storage/path',
                'storage_max_reports' => 10,
                'exclude_patterns' => [
                    '#/admin#',
                    '#/logout#',
                ],
                'respect_robots_txt' => false,
            ],
        ];

        $extension->load($customConfig, $container);

        $this->assertSame('https://example.com', $container->getParameter('on_page_seo.base_url'));
        $this->assertSame(5, $container->getParameter('on_page_seo.max_depth'));
        $this->assertSame(20, $container->getParameter('on_page_seo.timeout'));
        $this->assertSame('CustomBot/2.0', $container->getParameter('on_page_seo.user_agent'));
        $this->assertSame(['#/admin#', '#/logout#'], $container->getParameter('on_page_seo.exclude_patterns'));
        $this->assertSame('/custom/storage/path', $container->getParameter('on_page_seo.storage_dir'));
        $this->assertSame(10, $container->getParameter('on_page_seo.storage_max_reports'));
        $this->assertFalse($container->getParameter('on_page_seo.respect_robots_txt'));

        $this->assertTrue($container->hasDefinition(PageAuditor::class));
        $this->assertTrue($container->hasDefinition(SiteCrawler::class));
        $this->assertTrue($container->hasDefinition(CheckSeoCommand::class));
        $this->assertTrue($container->hasDefinition(JsonFileReportStorage::class));
        $this->assertTrue($container->hasDefinition(RobotsTxtChecker::class));

        $this->assertTrue(
            $container->hasAlias(CrawlerInterface::class)
            || $container->hasDefinition(CrawlerInterface::class)
        );
        $this->assertTrue(
            $container->hasAlias(ReportStorageInterface::class)
            || $container->hasDefinition(ReportStorageInterface::class)
        );
        $this->assertTrue(
            $container->hasAlias(RobotsTxtCheckerInterface::class)
            || $container->hasDefinition(RobotsTxtCheckerInterface::class)
        );
    }

    public function testNullStorageDirDisablesReportStorageService(): void
    {
        $container = $this->createContainer();
        $bundle = new OnPageSeoBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertNotNull($extension);

        $extension->load(['on_page_seo' => ['storage_dir' => null]], $container);

        $this->assertNull($container->getParameter('on_page_seo.storage_dir'));
        $this->assertFalse($container->hasDefinition(JsonFileReportStorage::class));
        $this->assertFalse(
            $container->hasAlias(ReportStorageInterface::class)
            || $container->hasDefinition(ReportStorageInterface::class)
        );
    }

    public function testEmptyStorageDirDisablesReportStorageService(): void
    {
        $container = $this->createContainer();
        $bundle = new OnPageSeoBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertNotNull($extension);

        $extension->load(['on_page_seo' => ['storage_dir' => '']], $container);

        $this->assertFalse($container->hasDefinition(JsonFileReportStorage::class));
    }

    private function createContainer(): ContainerBuilder
    {
        $tempDir = sys_get_temp_dir();

        return new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => $tempDir,
            'kernel.build_dir' => $tempDir,
            'kernel.cache_dir' => $tempDir,
            'kernel.charset' => 'UTF-8',
            'kernel.environment' => 'test',
        ]));
    }
}
