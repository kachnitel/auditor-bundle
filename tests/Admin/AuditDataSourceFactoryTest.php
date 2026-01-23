<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Admin;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface;
use Kachnitel\AuditorBundle\Admin\AuditDataSource;
use Kachnitel\AuditorBundle\Admin\AuditDataSourceFactory;
use Kachnitel\AuditorBundle\Service\AuditReader;
use PHPUnit\Framework\Attributes\Small;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @internal
 */
#[Small]
final class AuditDataSourceFactoryTest extends KernelTestCase
{
    use BlogSchemaSetupTrait;
    use ReaderTrait;

    private DoctrineProvider $provider;
    private Reader $reader;
    private AuditReader $auditReader;
    private AuditDataSourceFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->provider = self::getContainer()->get(DoctrineProvider::class);
        $this->configureEntities();
        $this->setupEntitySchemas();
        $this->setupAuditSchemas();

        $this->reader = $this->createReader();
        $this->auditReader = new AuditReader($this->reader);
        $this->factory = new AuditDataSourceFactory($this->reader, $this->auditReader);
    }

    public function testImplementsDataSourceProviderInterface(): void
    {
        $this->assertInstanceOf(DataSourceProviderInterface::class, $this->factory);
    }

    public function testCreateReturnsDataSourceForAuditableEntity(): void
    {
        $dataSource = $this->factory->create(Author::class);

        $this->assertInstanceOf(AuditDataSource::class, $dataSource);
        $this->assertSame(Author::class, $dataSource->getEntityClass());
    }

    public function testCreateReturnsNullForNonAuditableEntity(): void
    {
        $dataSource = $this->factory->create(\stdClass::class);

        $this->assertNull($dataSource);
    }

    public function testCreateAllReturnsDataSourcesForAllAuditedEntities(): void
    {
        $dataSources = $this->factory->createAll();

        $this->assertIsArray($dataSources);
        $this->assertNotEmpty($dataSources);

        // Verify all returned items are AuditDataSource instances
        foreach ($dataSources as $dataSource) {
            $this->assertInstanceOf(AuditDataSource::class, $dataSource);
        }

        // Verify Author entity has a data source
        $authorDataSource = $this->factory->get('audit-DH-Auditor-Tests-Provider-Doctrine-Fixtures-Entity-Standard-Blog-Author');
        $this->assertInstanceOf(AuditDataSource::class, $authorDataSource);
        $this->assertSame(Author::class, $authorDataSource->getEntityClass());
    }

    public function testGetDataSourcesReturnsIterable(): void
    {
        $dataSources = $this->factory->getDataSources();

        $this->assertIsIterable($dataSources);

        $dataSourcesArray = [...$dataSources];
        $this->assertNotEmpty($dataSourcesArray);

        foreach ($dataSourcesArray as $dataSource) {
            $this->assertInstanceOf(AuditDataSource::class, $dataSource);
        }
    }

    public function testGetReturnsNullForUnknownIdentifier(): void
    {
        $result = $this->factory->get('unknown-identifier');

        $this->assertNull($result);
    }

    public function testGetHandlesItemIdentifierWithSlash(): void
    {
        // Populate cache first
        $this->factory->createAll();

        // Test with item ID appended (e.g., "datasource-id/item-id")
        $dataSourceId = 'audit-DH-Auditor-Tests-Provider-Doctrine-Fixtures-Entity-Standard-Blog-Author';
        $itemIdentifier = $dataSourceId.'/12345';

        $dataSource = $this->factory->get($itemIdentifier);

        $this->assertInstanceOf(AuditDataSource::class, $dataSource);
        $this->assertSame(Author::class, $dataSource->getEntityClass());
        $this->assertSame($dataSourceId, $dataSource->getIdentifier());
    }

    public function testGetReturnsCachedDataSource(): void
    {
        // First call populates cache
        $this->factory->createAll();

        // Get by identifier
        $dataSource = $this->factory->get('audit-DH-Auditor-Tests-Provider-Doctrine-Fixtures-Entity-Standard-Blog-Author');

        $this->assertInstanceOf(AuditDataSource::class, $dataSource);
        $this->assertSame(Author::class, $dataSource->getEntityClass());
    }

    public function testClearCache(): void
    {
        // First call populates cache
        $dataSources1 = $this->factory->createAll();

        // Clear cache
        $this->factory->clearCache();

        // After clearing, createAll should create new instances
        $dataSources2 = $this->factory->createAll();

        // The arrays should contain the same data but be different instances
        $this->assertCount(\count($dataSources1), $dataSources2);

        // Get instances from both - they should be different objects after cache clear
        $id = 'audit-DH-Auditor-Tests-Provider-Doctrine-Fixtures-Entity-Standard-Blog-Author';

        // Both should exist
        $ds1 = $this->findDataSourceById($dataSources1, $id);
        $ds2 = $this->findDataSourceById($dataSources2, $id);

        $this->assertNotNull($ds1);
        $this->assertNotNull($ds2);
        // After cache clear, new objects should be created
        $this->assertNotSame($ds1, $ds2);
    }

    public function testCachingBehavior(): void
    {
        // First call
        $result1 = $this->factory->createAll();

        // Second call should use cache (same instances)
        $result2 = $this->factory->createAll();

        $this->assertSame($result1, $result2);
    }

    /**
     * @param array<AuditDataSource> $dataSources
     */
    private function findDataSourceById(array $dataSources, string $id): ?AuditDataSource
    {
        foreach ($dataSources as $ds) {
            if ($ds->getIdentifier() === $id) {
                return $ds;
            }
        }

        return null;
    }
}
