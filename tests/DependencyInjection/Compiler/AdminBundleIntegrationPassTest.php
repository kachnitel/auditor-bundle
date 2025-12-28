<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Tests\DependencyInjection\Compiler;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\AuditorBundle\Admin\AuditDataSourceFactory;
use DH\AuditorBundle\DependencyInjection\Compiler\AdminBundleIntegrationPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use PHPUnit\Framework\Attributes\Small;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Small]
final class AdminBundleIntegrationPassTest extends AbstractCompilerPassTestCase
{
    public function testRegistersFactoryWhenAdminBundleIsAvailable(): void
    {
        // Skip test if admin-bundle is not installed
        if (!interface_exists('Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface')) {
            $this->markTestSkipped('admin-bundle is not installed');
        }

        // Register the Reader service that the pass depends on
        $readerDefinition = new Definition(Reader::class);
        $this->setDefinition(Reader::class, $readerDefinition);

        $this->compile();

        // Verify that the factory was registered
        $this->assertContainerBuilderHasService(AuditDataSourceFactory::class);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            AuditDataSourceFactory::class,
            'admin.data_source_provider'
        );
        $this->assertContainerBuilderHasAlias('dh_auditor.admin.data_source_factory', AuditDataSourceFactory::class);
    }

    public function testDoesNotRegisterFactoryWhenReaderIsUnavailable(): void
    {
        // Skip test if admin-bundle is not installed
        if (!interface_exists('Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface')) {
            $this->markTestSkipped('admin-bundle is not installed');
        }

        // Don't register the Reader service
        $this->compile();

        // Verify that the factory was NOT registered (no Reader available)
        $this->assertFalse($this->container->has(AuditDataSourceFactory::class));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AdminBundleIntegrationPass());
    }
}
