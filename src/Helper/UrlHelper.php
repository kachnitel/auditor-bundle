<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Helper;

use Kachnitel\AuditorBundle\Tests\Helper\UrlHelperTest;

/**
 * @see UrlHelperTest
 */
abstract class UrlHelper
{
    public static function paramToNamespace(string $entity): string
    {
        return str_replace('-', '\\', $entity);
    }

    public static function namespaceToParam(string $entity): string
    {
        return str_replace('\\', '-', $entity);
    }
}
