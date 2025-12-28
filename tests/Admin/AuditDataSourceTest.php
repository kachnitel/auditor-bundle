<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Tests\Admin;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use DH\AuditorBundle\Admin\AuditDataSource;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use PHPUnit\Framework\Attributes\Small;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @internal
 */
#[Small]
final class AuditDataSourceTest extends KernelTestCase
{
    use BlogSchemaSetupTrait;
    use ReaderTrait;

    private DoctrineProvider $provider;
    private Reader $reader;
    private AuditDataSource $dataSource;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->provider = self::getContainer()->get(DoctrineProvider::class);
        $this->configureEntities();
        $this->setupEntitySchemas();
        $this->setupAuditSchemas();

        $this->reader = $this->createReader();
        $this->dataSource = new AuditDataSource($this->reader, Author::class);
    }

    public function testImplementsDataSourceInterface(): void
    {
        $this->assertInstanceOf(DataSourceInterface::class, $this->dataSource);
    }

    public function testGetIdentifier(): void
    {
        $this->assertSame(
            'audit-DH-Auditor-Tests-Provider-Doctrine-Fixtures-Entity-Standard-Blog-Author',
            $this->dataSource->getIdentifier()
        );
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Audit: Author', $this->dataSource->getLabel());
    }

    public function testGetIcon(): void
    {
        $this->assertSame('history', $this->dataSource->getIcon());
    }

    public function testGetColumns(): void
    {
        $columns = $this->dataSource->getColumns();

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('object_id', $columns);
        $this->assertArrayHasKey('type', $columns);
        $this->assertArrayHasKey('blame_user', $columns);
        $this->assertArrayHasKey('created_at', $columns);
        $this->assertArrayHasKey('diffs', $columns);

        $this->assertInstanceOf(ColumnMetadata::class, $columns['id']);
        $this->assertSame('ID', $columns['id']->label);
        $this->assertSame('integer', $columns['id']->type);
        $this->assertTrue($columns['id']->sortable);
    }

    public function testGetFilters(): void
    {
        $filters = $this->dataSource->getFilters();

        $this->assertArrayHasKey('object_id', $filters);
        $this->assertArrayHasKey('type', $filters);
        $this->assertArrayHasKey('created_at', $filters);
        $this->assertArrayHasKey('blame_user', $filters);
        $this->assertArrayHasKey('transaction_hash', $filters);

        $this->assertInstanceOf(FilterMetadata::class, $filters['object_id']);
        $this->assertSame('text', $filters['object_id']->type);

        $this->assertInstanceOf(FilterMetadata::class, $filters['type']);
        $this->assertSame('enum', $filters['type']->type);
    }

    public function testGetDefaultSortBy(): void
    {
        $this->assertSame('created_at', $this->dataSource->getDefaultSortBy());
    }

    public function testGetDefaultSortDirection(): void
    {
        $this->assertSame('DESC', $this->dataSource->getDefaultSortDirection());
    }

    public function testGetDefaultItemsPerPage(): void
    {
        $this->assertSame(50, $this->dataSource->getDefaultItemsPerPage());
    }

    public function testSupportsAction(): void
    {
        $this->assertTrue($this->dataSource->supportsAction('index'));
        $this->assertTrue($this->dataSource->supportsAction('show'));
        $this->assertFalse($this->dataSource->supportsAction('create'));
        $this->assertFalse($this->dataSource->supportsAction('update'));
        $this->assertFalse($this->dataSource->supportsAction('delete'));
    }

    public function testGetIdField(): void
    {
        $this->assertSame('id', $this->dataSource->getIdField());
    }

    public function testGetEntityClass(): void
    {
        $this->assertSame(Author::class, $this->dataSource->getEntityClass());
    }

    public function testGetShortName(): void
    {
        $this->assertSame('Author', $this->dataSource->getShortName());
    }

    public function testGetItemIdWithEntry(): void
    {
        // Create an author to generate an audit entry
        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');

        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Query the audit entries
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $this->assertGreaterThan(0, $result->totalItems);

        $entry = $result->items[0];
        $this->assertInstanceOf(Entry::class, $entry);

        $itemId = $this->dataSource->getItemId($entry);
        $this->assertIsInt($itemId);
        $this->assertGreaterThan(0, $itemId);
    }

    public function testGetItemValue(): void
    {
        // Create an author to generate an audit entry
        $author = new Author();
        $author->setFullname('John Doe')->setEmail('john@example.com');

        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Query the audit entries
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        $this->assertIsInt($this->dataSource->getItemValue($entry, 'id'));
        $this->assertSame((string) $author->getId(), $this->dataSource->getItemValue($entry, 'object_id'));
        $this->assertSame('insert', $this->dataSource->getItemValue($entry, 'type'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->dataSource->getItemValue($entry, 'created_at'));
        $this->assertIsArray($this->dataSource->getItemValue($entry, 'diffs'));
        $this->assertNull($this->dataSource->getItemValue($entry, 'unknown_field'));
    }

    public function testQueryReturnsEmptyResultWhenNoEntries(): void
    {
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertSame([], $result->items);
        $this->assertSame(0, $result->totalItems);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(50, $result->itemsPerPage);
    }

    public function testQueryWithEntries(): void
    {
        // Create multiple authors
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        for ($i = 1; $i <= 3; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);

        $this->assertSame(3, $result->totalItems);
        $this->assertCount(3, $result->items);
        $this->assertSame(1, $result->currentPage);
    }

    public function testQueryPagination(): void
    {
        // Create multiple authors
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        for ($i = 1; $i <= 5; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // First page with 2 items per page
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 2);

        $this->assertSame(5, $result->totalItems);
        $this->assertCount(2, $result->items);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(2, $result->itemsPerPage);

        // Second page
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 2, 2);

        $this->assertSame(5, $result->totalItems);
        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->currentPage);
    }

    public function testQueryClampsPageToValidRange(): void
    {
        // Create 3 authors (3 audit entries)
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        for ($i = 1; $i <= 3; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Request page 10 when only 1 page exists (3 items, 50 per page)
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 10, 50);

        $this->assertSame(1, $result->currentPage);
    }

    public function testQueryWithObjectIdFilter(): void
    {
        // Create two authors
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author1 = new Author();
        $author1->setFullname('Author 1')->setEmail('author1@example.com');
        $em->persist($author1);

        $author2 = new Author();
        $author2->setFullname('Author 2')->setEmail('author2@example.com');
        $em->persist($author2);

        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Filter by specific object_id
        $result = $this->dataSource->query('', ['object_id' => (string) $author1->getId()], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
        $this->assertSame((string) $author1->getId(), $result->items[0]->getObjectId());
    }

    public function testQueryWithTypeFilter(): void
    {
        // Create and then update an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Original Name')->setEmail('author@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $author->setFullname('Updated Name');
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Filter for only 'update' type
        $result = $this->dataSource->query('', ['type' => 'update'], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
        $this->assertSame('update', $result->items[0]->getType());
    }

    public function testFindReturnsEntry(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Get the entry via query first
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entryId = $result->items[0]->getId();

        // Now find by ID
        $entry = $this->dataSource->find($entryId);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame($entryId, $entry->getId());
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $result = $this->dataSource->find(99999);

        $this->assertNull($result);
    }

    public function testQuerySorting(): void
    {
        // Create multiple authors
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        for ($i = 1; $i <= 3; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
            $em->flush();
            $this->flushAll([Author::class => $auditingService]);
        }

        // Query with different sort fields - verify no errors are thrown
        $resultById = $this->dataSource->query('', [], 'id', 'ASC', 1, 50);
        $resultByCreatedAt = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $resultByType = $this->dataSource->query('', [], 'type', 'ASC', 1, 50);
        $resultByObjectId = $this->dataSource->query('', [], 'object_id', 'DESC', 1, 50);

        // All queries should return the same 3 entries
        $this->assertCount(3, $resultById->items);
        $this->assertCount(3, $resultByCreatedAt->items);
        $this->assertCount(3, $resultByType->items);
        $this->assertCount(3, $resultByObjectId->items);
    }

    public function testQueryWithInvalidSortFieldUsesDefault(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Should not throw, uses default sort
        $result = $this->dataSource->query('', [], 'invalid_field', 'ASC', 1, 50);

        $this->assertSame(1, $result->totalItems);
    }
}
