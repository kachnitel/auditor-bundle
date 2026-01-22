<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\DependencyInjection\Compiler;

use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AuditorBundle\Admin\AuditDataSourceFactory;
use Kachnitel\AuditorBundle\Controller\AuditIndexController;
use Kachnitel\AuditorBundle\Controller\TimelineController;
use Kachnitel\AuditorBundle\Routing\AuditorRouteLoader;
use Kachnitel\AuditorBundle\Service\AuditReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers audit admin integration when admin-bundle is installed.
 *
 * This compiler pass checks if kachnitel/admin-bundle is available and,
 * if so, registers:
 * - AuditDataSourceFactory as a data source provider
 * - AuditIndexController for audit data source views with "Hide System Events" toggle
 * - TimelineController for cross-entity audit timeline view
 * - AuditorRouteLoader for automatic route registration
 */
class AdminBundleIntegrationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Check if admin-bundle is available by looking for its core interface
        if (!interface_exists('Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface')) {
            return;
        }

        // Check if Reader service is available
        if (!$container->has('DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader')) {
            return;
        }

        $this->registerDataSourceFactory($container);
        $this->registerTimelineController($container);
        $this->registerRouteLoader($container);

        // AuditIndexController requires DataSourceRegistry service which may not be available
        if ($container->has(DataSourceRegistry::class)) {
            $this->registerAuditIndexController($container);
        }
    }

    private function registerDataSourceFactory(ContainerBuilder $container): void
    {
        // Register AuditDataSourceFactory as a data source provider
        // It implements DataSourceProviderInterface and provides audit data sources
        // for all audited entities
        $factoryDefinition = new Definition(AuditDataSourceFactory::class);
        $factoryDefinition->setArgument(0, new Reference('DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader'));
        $factoryDefinition->setArgument(1, new Reference(AuditReader::class));
        // Tag with interface FQCN for AutowireIterator discovery in admin-bundle
        $factoryDefinition->addTag('Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface');
        $factoryDefinition->setPublic(false);

        $container->setDefinition(AuditDataSourceFactory::class, $factoryDefinition);
        $container->setAlias('kachnitel_auditor.admin.data_source_factory', AuditDataSourceFactory::class);
    }

    private function registerAuditIndexController(ContainerBuilder $container): void
    {
        // Register AuditIndexController for audit data source views with "Hide System Events" toggle
        $controllerDefinition = new Definition(AuditIndexController::class);
        $controllerDefinition->setArgument(0, new Reference(DataSourceRegistry::class));
        $controllerDefinition->addTag('controller.service_arguments');
        $controllerDefinition->addTag('container.service_subscriber');
        $controllerDefinition->setAutowired(true);
        $controllerDefinition->setAutoconfigured(true);
        $controllerDefinition->setPublic(true);

        $container->setDefinition(AuditIndexController::class, $controllerDefinition);
    }

    private function registerTimelineController(ContainerBuilder $container): void
    {
        // Register TimelineController for cross-entity audit timeline view
        $controllerDefinition = new Definition(TimelineController::class);
        $controllerDefinition->setArgument(0, new Reference(AuditReader::class));
        $controllerDefinition->addTag('controller.service_arguments');
        $controllerDefinition->addTag('container.service_subscriber');
        $controllerDefinition->setAutowired(true);
        $controllerDefinition->setAutoconfigured(true);
        $controllerDefinition->setPublic(true);

        $container->setDefinition(TimelineController::class, $controllerDefinition);
    }

    private function registerRouteLoader(ContainerBuilder $container): void
    {
        // Register AuditorRouteLoader for automatic route registration
        $loaderDefinition = new Definition(AuditorRouteLoader::class);
        $loaderDefinition->addTag('routing.loader');

        $container->setDefinition(AuditorRouteLoader::class, $loaderDefinition);
    }
}
