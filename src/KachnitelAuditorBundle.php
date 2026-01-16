<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle;

use Kachnitel\AuditorBundle\DependencyInjection\Compiler\AddProviderCompilerPass;
use Kachnitel\AuditorBundle\DependencyInjection\Compiler\AdminBundleIntegrationPass;
use Kachnitel\AuditorBundle\DependencyInjection\Compiler\CustomConfigurationCompilerPass;
use Kachnitel\AuditorBundle\DependencyInjection\Compiler\DoctrineProviderConfigurationCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @see Tests\KachnitelAuditorBundleTest
 */
class KachnitelAuditorBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddProviderCompilerPass());
        $container->addCompilerPass(new DoctrineProviderConfigurationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
        $container->addCompilerPass(new CustomConfigurationCompilerPass());
        $container->addCompilerPass(new AdminBundleIntegrationPass());
    }
}
