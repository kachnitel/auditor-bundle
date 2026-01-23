<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\DependencyInjection;

use DH\Auditor\Provider\ProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

class KachnitelAuditorExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        // No prepend configuration needed - PHP components are auto-discovered
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $auditorConfig = $config;
        unset($auditorConfig['providers']);
        $container->setParameter('kachnitel_auditor.configuration', $auditorConfig);

        $this->loadProviders($container, $config);
    }

    private function loadProviders(ContainerBuilder $container, array $config): void
    {
        foreach ($config['providers'] as $providerName => $providerConfig) {
            $container->setParameter('kachnitel_auditor.provider.'.$providerName.'.configuration', $providerConfig);

            $container->registerAliasForArgument('kachnitel_auditor.provider.'.$providerName, ProviderInterface::class, \sprintf('%sProvider', $providerName));
        }
    }
}
