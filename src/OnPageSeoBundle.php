<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

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
            ->end()
        ;
        // @formatter:on
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $container->parameters()
            ->set('on_page_seo.base_url', $config['base_url'])
            ->set('on_page_seo.max_depth', $config['max_depth'])
            ->set('on_page_seo.timeout', $config['timeout'])
            ->set('on_page_seo.max_title_length', $config['max_title_length'])
            ->set('on_page_seo.max_description_length', $config['max_description_length'])
            ->set('on_page_seo.exclude_patterns', $config['exclude_patterns']);
    }
}
