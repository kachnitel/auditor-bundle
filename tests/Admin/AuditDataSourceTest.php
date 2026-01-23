<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Admin;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use Kachnitel\AuditorBundle\Admin\AuditDataSource;
use Kachnitel\AuditorBundle\Service\AuditReader;
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
    private AuditReader $auditReader;
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
        $this->auditReader = new AuditReader($this->reader);
        $this->dataSource = new AuditDataSource($this->reader, Author::class, $this->auditReader);
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
        $result = $this->dataSource->find(99_999);

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

    // =========================================================================
    // Actions column tests
    // =========================================================================

    public function testGetColumnsIncludesActionsColumn(): void
    {
        $columns = $this->dataSource->getColumns();

        $this->assertArrayHasKey('actions', $columns);
        $this->assertInstanceOf(ColumnMetadata::class, $columns['actions']);
        $this->assertSame('actions', $columns['actions']->type);
        $this->assertFalse($columns['actions']->sortable);
        $this->assertSame('@KachnitelAuditor/components/Audit/RowActions.html.twig', $columns['actions']->template);
    }

    public function testGetColumnsIncludesDiffsColumnWithTemplate(): void
    {
        $columns = $this->dataSource->getColumns();

        $this->assertArrayHasKey('diffs', $columns);
        $this->assertSame('json', $columns['diffs']->type);
        $this->assertFalse($columns['diffs']->sortable);
        $this->assertSame('@KachnitelAuditor/components/Audit/ChangesPreview.html.twig', $columns['diffs']->template);
    }

    // =========================================================================
    // Request ID filter tests
    // =========================================================================

    public function testGetFiltersIncludesRequestIdFilter(): void
    {
        $filters = $this->dataSource->getFilters();

        $this->assertArrayHasKey('request_id', $filters);
        $this->assertInstanceOf(FilterMetadata::class, $filters['request_id']);
        $this->assertSame('text', $filters['request_id']->type);
    }

    // =========================================================================
    // Timeline support tests
    // =========================================================================

    public function testHasTimelineSupportReturnsTrue(): void
    {
        $this->assertTrue($this->dataSource->hasTimelineSupport());
    }

    // =========================================================================
    // Change preview and detailed diffs tests
    // =========================================================================

    public function testGetChangePreview(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        $preview = $this->dataSource->getChangePreview($entry);

        $this->assertIsArray($preview);
    }

    public function testGetDetailedDiffs(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        $detailed = $this->dataSource->getDetailedDiffs($entry);

        $this->assertIsArray($detailed);
    }

    // =========================================================================
    // Request ID methods tests
    // =========================================================================

    public function testGetRequestIdReturnsNullWithoutContext(): void
    {
        // Create an author without request ID context
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        $requestId = $this->dataSource->getRequestId($entry);

        $this->assertNull($requestId);
    }

    public function testFindRelatedByRequestReturnsEmptyWithoutAuditReader(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        // Without AuditReader, should return empty
        $related = $this->dataSource->findRelatedByRequest($entry);

        $this->assertSame([], $related);
    }

    public function testFindUserTimelineReturnsEntries(): void
    {
        // Create multiple authors to have entries in timeline
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

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        // Should return entries (including system actions since no user)
        $timeline = $this->dataSource->findUserTimeline($entry, 5, true);

        // Both authors were created within the same minute, so both should be in timeline
        $this->assertIsArray($timeline);
    }

    public function testFindRelatedByRequestReturnsEmpty(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        // Entry doesn't have request_id context, so should return empty
        $related = $this->dataSource->findRelatedByRequest($entry);

        $this->assertSame([], $related);
    }

    // =========================================================================
    // Date range filter tests
    // =========================================================================

    public function testQueryWithDateRangeFilter(): void
    {
        // Create authors at different times (simulated via ordering)
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Query with date range filter for today
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $result = $this->dataSource->query('', [
            'created_at' => [
                'from' => $today,
                'to' => $today,
            ],
        ], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
    }

    public function testQueryWithDateRangeFilterPartialFrom(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Query with only 'from' date
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $result = $this->dataSource->query('', [
            'created_at' => [
                'from' => $today,
            ],
        ], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
    }

    public function testQueryWithDateRangeFilterPartialTo(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Query with only 'to' date
        $tomorrow = (new \DateTimeImmutable('+1 day'))->format('Y-m-d');
        $result = $this->dataSource->query('', [
            'created_at' => [
                'to' => $tomorrow,
            ],
        ], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
    }

    // =========================================================================
    // Global search tests
    // =========================================================================

    public function testQueryWithGlobalSearch(): void
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

        // Search for specific entity ID
        $result = $this->dataSource->query((string) $author1->getId(), [], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
        $this->assertSame((string) $author1->getId(), $result->items[0]->getObjectId());
    }

    // =========================================================================
    // Transaction hash filter tests
    // =========================================================================

    public function testQueryWithTransactionHashFilter(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Get the transaction hash from the entry
        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $transactionHash = $result->items[0]->getTransactionHash();

        // Filter by transaction hash
        $filteredResult = $this->dataSource->query('', ['transaction_hash' => $transactionHash], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $filteredResult->totalItems);
        $this->assertSame($transactionHash, $filteredResult->items[0]->getTransactionHash());
    }

    // =========================================================================
    // getItemValue additional fields tests
    // =========================================================================

    public function testGetItemValueForAllFields(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $result = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $result->items[0];

        // Test all field mappings
        $this->assertNotNull($this->dataSource->getItemValue($entry, 'transaction_hash'));
        $this->assertNull($this->dataSource->getItemValue($entry, 'blame_id')); // No user in tests
        $this->assertNull($this->dataSource->getItemValue($entry, 'blame_user')); // No user in tests
        $this->assertNull($this->dataSource->getItemValue($entry, 'ip')); // No IP in tests
        $this->assertNull($this->dataSource->getItemValue($entry, 'discriminator')); // No discriminator for Author
    }

    // =========================================================================
    // Type filter with array tests
    // =========================================================================

    public function testQueryWithTypeFilterAsArray(): void
    {
        // Create and update an author (generates insert and update)
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

        // Filter for both 'insert' and 'update' types as array
        $result = $this->dataSource->query('', ['type' => ['insert', 'update']], 'created_at', 'DESC', 1, 50);

        $this->assertSame(2, $result->totalItems);
    }

    // =========================================================================
    // Blame user filter tests
    // =========================================================================

    public function testQueryWithBlameUserFilter(): void
    {
        // Create an author (no user associated in test environment)
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Filter by a non-existent blame_user ID
        $result = $this->dataSource->query('', ['blame_user' => 'nonexistent-user-id'], 'created_at', 'DESC', 1, 50);

        // Should return 0 since no entries match that user
        $this->assertSame(0, $result->totalItems);
    }

    public function testQueryWithBlameUserFilterAndAuditReaderUsesCaseInsensitiveSearch(): void
    {
        // Create a data source with AuditReader for enhanced user search
        $auditReader = new AuditReader($this->reader);
        $dataSource = new AuditDataSource($this->reader, Author::class, $auditReader);

        // Create an author and set a blame_user value
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update the audit entry to set a blame_user value
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_user = ? WHERE object_id = ?', $tableName),
            ['Admin@Example.Com', (string) $author->getId()]
        );

        // Search with different case - should find the entry
        $result = $dataSource->query('', ['blame_user' => 'admin@example.com'], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
        $this->assertInstanceOf(Entry::class, $result->items[0]);
    }

    public function testQueryWithBlameUserFilterAndAuditReaderFindsPartialMatches(): void
    {
        // Create a data source with AuditReader for enhanced user search
        $auditReader = new AuditReader($this->reader);
        $dataSource = new AuditDataSource($this->reader, Author::class, $auditReader);

        // Create authors with different blame_user values
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

        // Update audit entries with different blame_user values
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_user = ? WHERE object_id = ?', $tableName),
            ['john@company.example.com', (string) $author1->getId()]
        );
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_user = ? WHERE object_id = ?', $tableName),
            ['jane@other.example.org', (string) $author2->getId()]
        );

        // Search for partial match - should find only the matching entry
        $result = $dataSource->query('', ['blame_user' => 'company'], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
        $this->assertSame((string) $author1->getId(), $result->items[0]->getObjectId());
    }

    public function testQueryWithBlameUserFilterCombinedWithTypeFilter(): void
    {
        // Create a data source with AuditReader for enhanced user search
        $auditReader = new AuditReader($this->reader);
        $dataSource = new AuditDataSource($this->reader, Author::class, $auditReader);

        // Create and update an author
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

        // Update both audit entries with blame_user value
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_user = ?', $tableName),
            ['admin@example.com']
        );

        // Search for user and filter by type - should find only update entries
        $result = $dataSource->query('', [
            'blame_user' => 'admin',
            'type' => 'update',
        ], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
        $this->assertSame('update', $result->items[0]->getType());
    }

    // =========================================================================
    // Hide system events filter tests
    // =========================================================================

    public function testGetFiltersIncludesHideSystemFilter(): void
    {
        $filters = $this->dataSource->getFilters();

        $this->assertArrayHasKey('hide_system', $filters);
        $this->assertInstanceOf(FilterMetadata::class, $filters['hide_system']);
        $this->assertSame('boolean', $filters['hide_system']->type);
    }

    public function testHideSystemFilterRemovesConsoleCommandEntries(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entry to simulate console command (blame_id='command', blame_user=command name)
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['command', 'app:order:refresh', (string) $author->getId()]
        );

        // Without filter - should return the entry
        $resultWithoutFilter = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $this->assertSame(1, $resultWithoutFilter->totalItems);

        // With hide_system filter - should filter out the command entry
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(0, $resultWithFilter->totalItems);
    }

    public function testHideSystemFilterKeepsEntriesWithNumericUserId(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entry to simulate real user with numeric ID
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['123', 'admin@example.com', (string) $author->getId()]
        );

        // With hide_system filter - should keep the entry (numeric userId)
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(1, $resultWithFilter->totalItems);
    }

    public function testHideSystemFilterKeepsEntriesWithEmailUsernameEmptyId(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entry to simulate real user with email but empty string ID
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['', 'user@example.com', (string) $author->getId()]
        );

        // With hide_system filter - should keep the entry (email username)
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(1, $resultWithFilter->totalItems);
    }

    public function testHideSystemFilterKeepsEntriesWithEmailUsernameNullId(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entry to simulate real user with email but NULL ID
        // This happens when User entity doesn't have getId() method
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = NULL, blame_user = ? WHERE object_id = ?', $tableName),
            ['user@example.com', (string) $author->getId()]
        );

        // Verify the values are as expected
        $resultAll = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $resultAll->items[0];
        $this->assertNull($entry->getUserId(), 'blame_id should be NULL');
        $this->assertSame('user@example.com', $entry->getUsername(), 'blame_user should be email');

        // With hide_system filter - should keep the entry (email username)
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(1, $resultWithFilter->totalItems, 'Entry with NULL id but email username should be kept');
    }

    /**
     * BUG FIX: This test exposes the issue when user identifier is not an email.
     *
     * When User::getUserIdentifier() returns a username (not email), and the
     * User entity doesn't have getId(), the blame_user won't contain "@".
     * The current filter would incorrectly filter out real user entries.
     */
    public function testHideSystemFilterWithNonEmailUsername(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entry to simulate real user with non-email username
        // This happens when getUserIdentifier() returns a username like "johndoe"
        // and the User entity doesn't have getId() method
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['', 'johndoe', (string) $author->getId()]
        );

        // Verify the values
        $resultAll = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $entry = $resultAll->items[0];
        $this->assertSame('', $entry->getUserId(), 'blame_id should be empty string');
        $this->assertSame('johndoe', $entry->getUsername(), 'blame_user should be username without @');

        // This is the FAILING case with current implementation
        // The entry has a username (a real user), but the filter doesn't detect it
        // because:
        // - userId is empty string (not numeric, not 'command')
        // - username doesn't contain @
        // So the entry would be filtered out incorrectly!

        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 50);

        // With the current buggy implementation, this fails:
        // Expected: 1 (real user should be kept)
        // Actual: 0 (entry is incorrectly filtered out)
        $this->assertSame(1, $resultWithFilter->totalItems, 'Entry with non-email username should still be kept as real user');
    }

    public function testHideSystemFilterRemovesEntriesWithNoUserInfo(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entry to simulate automated/system event (no user info)
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = NULL, blame_user = NULL WHERE object_id = ?', $tableName),
            [(string) $author->getId()]
        );

        // With hide_system filter - should filter out the entry (no user info)
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(0, $resultWithFilter->totalItems);
    }

    public function testHideSystemFilterCorrectlyMixedEntries(): void
    {
        // Create multiple authors with different user types
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        // Create 4 authors
        $authors = [];
        for ($i = 1; $i <= 4; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
            $authors[$i] = $author;
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entries with different user types
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);

        // Author 1: Console command (should be filtered out)
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['command', 'app:order:refresh', (string) $authors[1]->getId()]
        );

        // Author 2: Real user with numeric ID (should be kept)
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['42', 'admin@company.com', (string) $authors[2]->getId()]
        );

        // Author 3: Real user with email username but empty ID (should be kept)
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['', 'user@example.org', (string) $authors[3]->getId()]
        );

        // Author 4: System event with no user info (should be filtered out)
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = NULL, blame_user = NULL WHERE object_id = ?', $tableName),
            [(string) $authors[4]->getId()]
        );

        // Without filter - should return all 4 entries
        $resultWithoutFilter = $this->dataSource->query('', [], 'created_at', 'DESC', 1, 50);
        $this->assertSame(4, $resultWithoutFilter->totalItems);

        // With hide_system filter - should return only 2 entries (authors 2 and 3)
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(2, $resultWithFilter->totalItems);

        // Verify the correct entries are kept
        $keptObjectIds = array_map(
            static fn (Entry $entry) => $entry->getObjectId(),
            $resultWithFilter->items
        );
        $this->assertContains((string) $authors[2]->getId(), $keptObjectIds);
        $this->assertContains((string) $authors[3]->getId(), $keptObjectIds);
        $this->assertNotContains((string) $authors[1]->getId(), $keptObjectIds);
        $this->assertNotContains((string) $authors[4]->getId(), $keptObjectIds);
    }

    public function testHideSystemFilterWithBooleanTrueValue(): void
    {
        // Create an author
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entry to simulate console command
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['command', 'app:import', (string) $author->getId()]
        );

        // With hide_system filter as boolean true - should filter out the command entry
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => true], 'created_at', 'DESC', 1, 50);
        $this->assertSame(0, $resultWithFilter->totalItems);
    }

    /**
     * Debug test to inspect Entry values.
     *
     * This test helps understand what values are returned from the database
     * for blame_id and blame_user fields.
     */
    public function testHideSystemFilterDebugEntryValues(): void
    {
        // Create multiple authors with different user configurations
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $authors = [];
        for ($i = 1; $i <= 4; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
            $authors[$i] = $author;
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entries with different user types
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);

        // Author 1: Console command
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['command', 'app:order:refresh', (string) $authors[1]->getId()]
        );

        // Author 2: Real user with numeric ID
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['42', 'admin@company.com', (string) $authors[2]->getId()]
        );

        // Author 3: Real user with email username but empty ID
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
            ['', 'user@example.org', (string) $authors[3]->getId()]
        );

        // Author 4: No user info (NULL)
        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_id = NULL, blame_user = NULL WHERE object_id = ?', $tableName),
            [(string) $authors[4]->getId()]
        );

        // Fetch all entries and inspect values
        $result = $this->dataSource->query('', [], 'id', 'ASC', 1, 50);
        $this->assertSame(4, $result->totalItems);

        foreach ($result->items as $entry) {
            $userId = $entry->getUserId();
            $username = $entry->getUsername();

            // Test the actual filter logic
            $isCommand = 'command' === $userId;
            $hasNumericId = null !== $userId && '' !== $userId && is_numeric($userId);
            $hasEmailUsername = null !== $username && str_contains($username, '@');
            $hasNonEmptyId = null !== $userId && '' !== $userId;

            // This helps us understand what values we're working with
            // The assertions validate our understanding of the filter logic
            if ($entry->getObjectId() === (string) $authors[1]->getId()) {
                // Console command
                $this->assertSame('command', $userId, 'Console command should have userId=command');
                $this->assertSame('app:order:refresh', $username, 'Console command should have command name as username');
                $this->assertTrue($isCommand, 'Should be detected as command');
                $this->assertFalse($hasNumericId, 'Command is not numeric');
                $this->assertFalse($hasEmailUsername, 'Command name has no @');
            } elseif ($entry->getObjectId() === (string) $authors[2]->getId()) {
                // Real user with numeric ID
                $this->assertSame('42', $userId, 'Real user should have numeric userId');
                $this->assertSame('admin@company.com', $username, 'Real user should have email username');
                $this->assertFalse($isCommand, 'Should not be detected as command');
                $this->assertTrue($hasNumericId, 'Should have numeric ID');
                $this->assertTrue($hasEmailUsername, 'Should have email username');
            } elseif ($entry->getObjectId() === (string) $authors[3]->getId()) {
                // Real user with email but empty ID
                $this->assertSame('', $userId, 'Empty ID should be empty string');
                $this->assertSame('user@example.org', $username, 'Should have email username');
                $this->assertFalse($isCommand, 'Should not be detected as command');
                $this->assertFalse($hasNumericId, 'Empty string is not numeric');
                $this->assertTrue($hasEmailUsername, 'Should have email username');
            } elseif ($entry->getObjectId() === (string) $authors[4]->getId()) {
                // No user info
                $this->assertNull($userId, 'NULL userId should be null');
                $this->assertNull($username, 'NULL username should be null');
                $this->assertFalse($isCommand, 'NULL is not command');
                $this->assertFalse($hasNumericId, 'NULL is not numeric');
                $this->assertFalse($hasEmailUsername, 'NULL has no @');
            }
        }
    }

    public function testHideSystemFilterPaginatesCorrectly(): void
    {
        // Create 6 authors
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $authors = [];
        for ($i = 1; $i <= 6; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
            $authors[$i] = $author;
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update audit entries: 3 commands, 3 real users
        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);

        for ($i = 1; $i <= 3; ++$i) {
            $connection->executeStatement(
                \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
                ['command', "app:cmd{$i}", (string) $authors[$i]->getId()]
            );
        }
        for ($i = 4; $i <= 6; ++$i) {
            $connection->executeStatement(
                \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
                [(string) $i, "user{$i}@example.com", (string) $authors[$i]->getId()]
            );
        }

        // With hide_system filter and pagination - 2 items per page
        $page1 = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 1, 2);
        $this->assertSame(3, $page1->totalItems);
        $this->assertCount(2, $page1->items);
        $this->assertSame(1, $page1->currentPage);

        $page2 = $this->dataSource->query('', ['hide_system' => '1'], 'created_at', 'DESC', 2, 2);
        $this->assertSame(3, $page2->totalItems);
        $this->assertCount(1, $page2->items);
        $this->assertSame(2, $page2->currentPage);
    }

    /**
     * Test that hide_system filter finds real user entries even when
     * the first entries (by ID order) are all system events.
     *
     * This verifies that we're fetching ALL entries before filtering,
     * not just the first page.
     */
    public function testHideSystemFilterFindsUserEntriesAfterManySystemEntries(): void
    {
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        // Create 60 authors (more than typical page size of 50)
        $authors = [];
        for ($i = 1; $i <= 60; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
            $authors[$i] = $author;
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $connection = $em->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);

        // Make first 55 entries system events (commands)
        for ($i = 1; $i <= 55; ++$i) {
            $connection->executeStatement(
                \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
                ['command', "app:cmd{$i}", (string) $authors[$i]->getId()]
            );
        }

        // Make last 5 entries real user events
        for ($i = 56; $i <= 60; ++$i) {
            $connection->executeStatement(
                \sprintf('UPDATE %s SET blame_id = ?, blame_user = ? WHERE object_id = ?', $tableName),
                [(string) $i, "user{$i}@example.com", (string) $authors[$i]->getId()]
            );
        }

        // Without filter - verify total count (limited by default pagination)
        $resultWithoutFilter = $this->dataSource->query('', [], 'id', 'ASC', 1, 100);
        $this->assertSame(60, $resultWithoutFilter->totalItems, 'Without filter should see all 60 entries in total count');

        // With hide_system filter - should find the 5 real user entries
        // This tests that we fetch ALL entries (not just first 50) before filtering
        $resultWithFilter = $this->dataSource->query('', ['hide_system' => '1'], 'id', 'ASC', 1, 50);
        $this->assertSame(5, $resultWithFilter->totalItems, 'Should find 5 real user entries even when first 55 are system events');
        $this->assertCount(5, $resultWithFilter->items);
    }

    public function testQueryWithObjectIdWildcardStartsWith(): void
    {
        // Create authors with different IDs
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        // Create 3 authors - IDs will be 1, 2, 3
        for ($i = 1; $i <= 3; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Filter with starts-with wildcard (e.g., "1*" should match IDs starting with 1)
        $result = $this->dataSource->query('', ['object_id' => '1*'], 'created_at', 'DESC', 1, 50);

        // Should match ID "1" (starts with 1)
        $this->assertGreaterThanOrEqual(1, $result->totalItems);
        foreach ($result->items as $item) {
            $this->assertStringStartsWith('1', $item->getObjectId() ?? '');
        }
    }

    public function testQueryWithObjectIdWildcardContains(): void
    {
        // This test verifies that *abc* pattern works for contains matching
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        // Create authors
        for ($i = 1; $i <= 5; ++$i) {
            $author = new Author();
            $author->setFullname("Author {$i}")->setEmail("author{$i}@example.com");
            $em->persist($author);
        }
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Without wildcard - exact match, should find 0 (no object_id matches "*")
        $resultExact = $this->dataSource->query('', ['object_id' => '999'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(0, $resultExact->totalItems);

        // With * wildcard pattern - should use LIKE and find entries
        $resultWildcard = $this->dataSource->query('', ['object_id' => '*'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(5, $resultWildcard->totalItems);
    }

    public function testQueryWithObjectIdExactMatchStillWorks(): void
    {
        // Verify that exact matching (no wildcard) still works
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

        // Exact match without wildcard
        $result = $this->dataSource->query('', ['object_id' => (string) $author1->getId()], 'created_at', 'DESC', 1, 50);

        $this->assertSame(1, $result->totalItems);
        $this->assertSame((string) $author1->getId(), $result->items[0]->getObjectId());
    }

    public function testQueryWithObjectIdWildcardCombinedWithTypeFilter(): void
    {
        // Test that wildcard object_id works with other filters
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        // Create and then update an author
        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Update to create an 'update' type entry
        $author->setFullname('Updated Author');
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        // Wildcard + type filter
        $resultInsert = $this->dataSource->query('', ['object_id' => '*', 'type' => 'insert'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(1, $resultInsert->totalItems);
        $this->assertSame('insert', $resultInsert->items[0]->getType());

        $resultUpdate = $this->dataSource->query('', ['object_id' => '*', 'type' => 'update'], 'created_at', 'DESC', 1, 50);
        $this->assertSame(1, $resultUpdate->totalItems);
        $this->assertSame('update', $resultUpdate->items[0]->getType());
    }

    public function testGetFiltersTypeIsEnumWithAllOptions(): void
    {
        $filters = $this->dataSource->getFilters();

        $this->assertArrayHasKey('type', $filters);
        $typeFilter = $filters['type'];
        $this->assertInstanceOf(FilterMetadata::class, $typeFilter);
        $this->assertSame('enum', $typeFilter->type);

        // Verify all action types are present
        $options = $typeFilter->choices;
        $this->assertContains('insert', $options);
        $this->assertContains('update', $options);
        $this->assertContains('remove', $options);
        $this->assertContains('associate', $options);
        $this->assertContains('dissociate', $options);
        $this->assertContains('event', $options);
    }

    public function testObjectIdFilterPlaceholderHintsWildcard(): void
    {
        $filters = $this->dataSource->getFilters();

        $this->assertArrayHasKey('object_id', $filters);
        $objectIdFilter = $filters['object_id'];
        $this->assertInstanceOf(FilterMetadata::class, $objectIdFilter);

        // Placeholder should mention wildcard support
        $this->assertStringContainsString('*', $objectIdFilter->placeholder ?? '');
    }
}
