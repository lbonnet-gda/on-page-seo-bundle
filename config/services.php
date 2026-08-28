<?php

declare(strict_types=1);

use Lbonnet\OnPageSeoBundle\Auditor\PageAuditor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

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
        ]);

    $services->set(PageAuditor::class)
        ->arg('$maxTitleLength', param('on_page_seo.max_title_length'))
        ->arg('$maxDescriptionLength', param('on_page_seo.max_description_length'));
};
