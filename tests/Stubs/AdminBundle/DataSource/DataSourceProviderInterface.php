<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Stub interface for testing when admin-bundle is not installed.
 *
 * @internal
 */
interface DataSourceProviderInterface
{
    /**
     * @return iterable<DataSourceInterface>
     */
    public function getDataSources(): iterable;
}
