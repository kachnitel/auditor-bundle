<?php

declare(strict_types=1);

namespace DH\AuditorBundle\DependencyInjection\Compiler;

use DH\AuditorBundle\Admin\AuditDataSourceFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers audit data sources with admin-bundle when it's installed.
 *
 * This compiler pass checks if kachnitel/admin-bundle is available and,
 * if so, registers the AuditDataSourceFactory as a data source provider.
 * The factory implements DataSourceProviderInterface and will be picked up
 * by the admin-bundle's DataSourceRegistry.
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

        // Register AuditDataSourceFactory as a data source provider
        // It implements DataSourceProviderInterface and provides audit data sources
        // for all audited entities
        $factoryDefinition = new Definition(AuditDataSourceFactory::class);
        $factoryDefinition->setArgument(0, new Reference('DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader'));
        $factoryDefinition->addTag('admin.data_source_provider');
        $factoryDefinition->setPublic(false);

        $container->setDefinition(AuditDataSourceFactory::class, $factoryDefinition);
        $container->setAlias('dh_auditor.admin.data_source_factory', AuditDataSourceFactory::class);
    }
}
