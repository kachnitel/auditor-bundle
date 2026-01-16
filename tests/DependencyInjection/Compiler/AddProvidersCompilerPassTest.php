<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\DependencyInjection\Compiler;

use Kachnitel\AuditorBundle\DependencyInjection\Compiler\AddProviderCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use PHPUnit\Framework\Attributes\Small;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Small]
final class AddProvidersCompilerPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddProviderCompilerPass());
    }
}
