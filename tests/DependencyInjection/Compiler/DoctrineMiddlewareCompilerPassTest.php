<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\DependencyInjection\Compiler;

use DH\Auditor\Configuration;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorMiddleware;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\DBAL\Driver\Middleware;
use Kachnitel\AuditorBundle\DependencyInjection\Compiler\DoctrineProviderConfigurationCompilerPass;
use Kachnitel\AuditorBundle\DependencyInjection\KachnitelAuditorExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use PHPUnit\Framework\Attributes\Small;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Small]
final class DoctrineMiddlewareCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testCompilerPass(): void
    {
        if (!interface_exists(Middleware::class) || !class_exists(AuditorMiddleware::class)) {
            self::markTestSkipped("AuditorMiddleware isn't supported");
        }

        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->container->setParameter('kernel.build_dir', $this->container->getParameter('kernel.cache_dir'));
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.bundles', []);

        $doctrineConfig = [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => [],
                ],
            ],
            'orm' => [
                'auto_mapping' => true,
            ],
        ];
        $this->setParameter('doctrine', $doctrineConfig);

        $DHConfig = [
            'enabled' => true,
            'timezone' => 'UTC',
            'user_provider' => 'kachnitel_auditor.user_provider',
            'security_provider' => 'kachnitel_auditor.security_provider',
            'role_checker' => 'kachnitel_auditor.role_checker',
            'providers' => [
                'doctrine' => [
                    'table_prefix' => '',
                    'table_suffix' => '_audit',
                    'ignored_columns' => [
                        0 => 'createdAt',
                        1 => 'updatedAt',
                    ],
                    'entities' => [
                        Author::class => [
                            'enabled' => true,
                        ],
                        Post::class => [
                            'enabled' => true,
                        ],
                        Comment::class => [
                            'enabled' => true,
                        ],
                        Tag::class => [
                            'enabled' => true,
                        ],
                    ],
                    'storage_services' => [
                        0 => '@doctrine.orm.default_entity_manager',
                    ],
                    'auditing_services' => [
                        0 => '@doctrine.orm.default_entity_manager',
                    ],
                    'viewer' => true,
                    'storage_mapper' => null,
                ],
            ],
        ];
        $this->setParameter('kachnitel_auditor.configuration', $DHConfig);

        $auditorService = new Definition();
        $this->setDefinition(Configuration::class, $auditorService);
        $this->container->loadFromExtension('doctrine', $doctrineConfig);
        $this->container->loadFromExtension('kachnitel_auditor', $DHConfig);
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'doctrine.dbal.default_connection.auditor_middleware',
            'doctrine.middleware'
        );
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $this->container->registerExtension(new DoctrineExtension());
        $this->container->registerExtension(new KachnitelAuditorExtension());
        $container->addCompilerPass(new DoctrineProviderConfigurationCompilerPass());
    }
}
