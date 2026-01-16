<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Admin;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use Kachnitel\AuditorBundle\Service\AuditReader;
use Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface;

/**
 * Factory for creating AuditDataSource instances.
 *
 * Creates a data source for each audited entity class.
 * Implements DataSourceProviderInterface for integration with admin-bundle.
 */
class AuditDataSourceFactory implements DataSourceProviderInterface
{
    /** @var null|array<string, AuditDataSource> */
    private ?array $dataSourcesCache = null;

    public function __construct(
        private readonly Reader $reader,
        private readonly AuditReader $auditReader,
    ) {}

    /**
     * Get all audit data sources.
     * Required by DataSourceProviderInterface.
     *
     * @return iterable<AuditDataSource>
     */
    public function getDataSources(): iterable
    {
        return $this->createAll();
    }

    /**
     * Create data sources for all audited entities.
     *
     * @return array<AuditDataSource>
     */
    public function createAll(): array
    {
        if (null !== $this->dataSourcesCache) {
            return array_values($this->dataSourcesCache);
        }

        $this->dataSourcesCache = [];

        $schemaManager = new SchemaManager($this->reader->getProvider());

        /** @var AuditingService[] $auditingServices */
        $auditingServices = $this->reader->getProvider()->getAuditingServices();

        foreach ($auditingServices as $auditingService) {
            $auditableTables = $schemaManager->getAuditableTableNames($auditingService->getEntityManager());

            foreach (array_keys($auditableTables) as $entityClass) {
                \assert(\is_string($entityClass) && class_exists($entityClass));
                $dataSource = new AuditDataSource($this->reader, $entityClass, $this->auditReader);
                $this->dataSourcesCache[$dataSource->getIdentifier()] = $dataSource;
            }
        }

        return array_values($this->dataSourcesCache);
    }

    /**
     * Create a data source for a specific entity class.
     *
     * @param class-string $entityClass
     */
    public function create(string $entityClass): ?AuditDataSource
    {
        if (!$this->reader->getProvider()->isAuditable($entityClass)) {
            return null;
        }

        return new AuditDataSource($this->reader, $entityClass, $this->auditReader);
    }

    /**
     * Get a data source by identifier.
     */
    public function get(string $identifier): ?AuditDataSource
    {
        // Ensure cache is populated
        $this->createAll();

        return $this->dataSourcesCache[$identifier] ?? null;
    }

    /**
     * Clear the cached data sources.
     */
    public function clearCache(): void
    {
        $this->dataSourcesCache = null;
    }
}
