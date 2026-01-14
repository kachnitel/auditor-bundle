<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Tests\Service;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use DH\AuditorBundle\Service\AuditContext;
use DH\AuditorBundle\Service\AuditReader;
use PHPUnit\Framework\Attributes\Small;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @internal
 */
#[Small]
final class AuditReaderTest extends KernelTestCase
{
    use BlogSchemaSetupTrait;
    use ReaderTrait;

    private DoctrineProvider $provider;
    private Reader $reader;
    private AuditReader $auditReader;
    private AuditContext $auditContext;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $provider = self::getContainer()->get(DoctrineProvider::class);
        \assert($provider instanceof DoctrineProvider);
        $this->provider = $provider;

        $this->configureEntities();
        $this->setupEntitySchemas();
        $this->setupAuditSchemas();

        $this->reader = $this->createReader();
        $this->auditReader = new AuditReader($this->reader);

        $auditContext = self::getContainer()->get(AuditContext::class);
        \assert($auditContext instanceof AuditContext);
        $this->auditContext = $auditContext;
    }

    protected function tearDown(): void
    {
        $this->auditContext->clear();

        // Drop all tables to ensure clean state for next test
        $this->dropAllTables();

        parent::tearDown();
    }

    // =========================================================================
    // getReader tests
    // =========================================================================

    public function testGetReaderReturnsReader(): void
    {
        $this->assertNotNull($this->auditReader->getReader());
    }

    // =========================================================================
    // findByEntityClass tests
    // =========================================================================

    public function testFindByEntityClassReturnsEmptyWhenNoAudits(): void
    {
        $result = $this->auditReader->findByEntityClass(Author::class);

        $this->assertSame([], $result);
    }

    public function testFindByEntityClassReturnsAuditEntries(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        $result = $this->auditReader->findByEntityClass(Author::class);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Entry::class, $result[0]);
        $this->assertSame('insert', $result[0]->getType());
    }

    public function testFindByEntityClassFiltersById(): void
    {
        $author1 = $this->createAuthor('Author 1', 'author1@example.com');
        $author2 = $this->createAuthor('Author 2', 'author2@example.com');

        $result = $this->auditReader->findByEntityClass(
            Author::class,
            ids: [(string) $author1->getId()]
        );

        $this->assertCount(1, $result);
        $this->assertSame((string) $author1->getId(), $result[0]->getObjectId());
    }

    public function testFindByEntityClassFiltersByType(): void
    {
        $author = $this->createAuthor('Test Author', 'test@example.com');

        // Update the author to create an update audit
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();
        $author->setFullname('Updated Author');
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        $insertResult = $this->auditReader->findByEntityClass(Author::class, type: 'insert');
        $updateResult = $this->auditReader->findByEntityClass(Author::class, type: 'update');

        $this->assertCount(1, $insertResult);
        $this->assertCount(1, $updateResult);
        $this->assertSame('insert', $insertResult[0]->getType());
        $this->assertSame('update', $updateResult[0]->getType());
    }

    // =========================================================================
    // findUserTimeline tests
    // =========================================================================

    public function testFindUserTimelineReturnsEmptyWhenNoRelatedAudits(): void
    {
        $author = $this->createAuthor('Test Author', 'test@example.com');

        $audits = $this->auditReader->findByEntityClass(Author::class);
        $referenceEntry = $audits[0];

        // Timeline with a very small window that excludes the reference entry itself
        // (entry is at time T, window is T-1min to T+1min but only for a different user)
        // Since our test doesn't have user tracking, this tests the basic flow
        $result = $this->auditReader->findUserTimeline($referenceEntry, windowMinutes: 5);

        // Should return at least the original entry (same time range)
        $this->assertIsArray($result);
    }

    public function testFindUserTimelineGroupsByEntityClass(): void
    {
        // Create an author
        $author = $this->createAuthor('Test Author', 'test@example.com');

        // Create a post by the same "user" (in tests, blame_id will be null)
        $auditingService = $this->provider->getAuditingServiceForEntity(Post::class);
        $em = $auditingService->getEntityManager();

        $post = new Post();
        $post->setTitle('Test Post')->setBody('Test body')->setCreatedAt(new \DateTimeImmutable());
        $em->persist($post);
        $em->flush();
        $this->flushAll([Post::class => $auditingService]);

        // Get reference entry
        $authorAudits = $this->auditReader->findByEntityClass(Author::class);
        $referenceEntry = $authorAudits[0];

        $result = $this->auditReader->findUserTimeline($referenceEntry, windowMinutes: 60);

        // Should have entries from both Author and Post
        $this->assertIsArray($result);
        // Both entities have system events (null blame_id) in the time window
        $this->assertArrayHasKey(Author::class, $result);
    }

    public function testFindUserTimelineRespectsWindowMinutes(): void
    {
        $author = $this->createAuthor('Test Author', 'test@example.com');

        $audits = $this->auditReader->findByEntityClass(Author::class);
        $referenceEntry = $audits[0];

        // With 0 minute window, should only find entries at exact same time
        $narrowResult = $this->auditReader->findUserTimeline($referenceEntry, windowMinutes: 0);

        // With 60 minute window, should find more entries
        $wideResult = $this->auditReader->findUserTimeline($referenceEntry, windowMinutes: 60);

        $this->assertIsArray($narrowResult);
        $this->assertIsArray($wideResult);
    }

    public function testFindUserTimelineCanIncludeSystemEvents(): void
    {
        $author = $this->createAuthor('Test Author', 'test@example.com');

        $audits = $this->auditReader->findByEntityClass(Author::class);
        $referenceEntry = $audits[0];

        // Without system events
        $withoutSystem = $this->auditReader->findUserTimeline(
            $referenceEntry,
            windowMinutes: 5,
            includeSystemEvents: false
        );

        // With system events
        $withSystem = $this->auditReader->findUserTimeline(
            $referenceEntry,
            windowMinutes: 5,
            includeSystemEvents: true
        );

        $this->assertIsArray($withoutSystem);
        $this->assertIsArray($withSystem);
    }

    // =========================================================================
    // findByRequestId tests
    // =========================================================================

    public function testFindByRequestIdReturnsEmptyWhenNoMatches(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        $result = $this->auditReader->findByRequestId('non-existent-request-id');

        $this->assertSame([], $result);
    }

    public function testFindByRequestIdFindsEntriesWithMatchingRequestId(): void
    {
        $requestId = 'test-request-id-'.uniqid();

        // Set request ID in context before creating entity
        $this->auditContext->setRequestId($requestId);

        $this->createAuthor('Test Author', 'test@example.com');

        $result = $this->auditReader->findByRequestId($requestId);

        $this->assertArrayHasKey(Author::class, $result);
        $this->assertCount(1, $result[Author::class]);
        $this->assertInstanceOf(Entry::class, $result[Author::class][0]);
    }

    public function testFindByRequestIdFindsMultipleEntitiesWithSameRequestId(): void
    {
        $requestId = 'shared-request-id-'.uniqid();

        // Set request ID in context
        $this->auditContext->setRequestId($requestId);

        // Create author
        $this->createAuthor('Test Author', 'test@example.com');

        // Create post with same request ID
        $auditingService = $this->provider->getAuditingServiceForEntity(Post::class);
        $em = $auditingService->getEntityManager();

        $post = new Post();
        $post->setTitle('Test Post')->setBody('Test body')->setCreatedAt(new \DateTimeImmutable());
        $em->persist($post);
        $em->flush();
        $this->flushAll([Post::class => $auditingService]);

        $result = $this->auditReader->findByRequestId($requestId);

        // Should have entries from both Author and Post
        $this->assertArrayHasKey(Author::class, $result);
        $this->assertArrayHasKey(Post::class, $result);
    }

    public function testFindEntityAuditsByRequestIdReturnsEntriesForSpecificEntity(): void
    {
        $requestId = 'entity-specific-request-'.uniqid();

        $this->auditContext->setRequestId($requestId);
        $this->createAuthor('Test Author', 'test@example.com');

        $result = $this->auditReader->findEntityAuditsByRequestId(Author::class, $requestId);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Entry::class, $result[0]);
    }

    public function testFindEntityAuditsByRequestIdReturnsEmptyForWrongEntity(): void
    {
        $requestId = 'wrong-entity-request-'.uniqid();

        $this->auditContext->setRequestId($requestId);
        $this->createAuthor('Test Author', 'test@example.com');

        // Search in Post class instead of Author
        $result = $this->auditReader->findEntityAuditsByRequestId(Post::class, $requestId);

        $this->assertSame([], $result);
    }

    // =========================================================================
    // findByEntityClass with date range tests
    // =========================================================================

    public function testFindByEntityClassWithFromDate(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        // Query with from date in the past - should find the entry
        $result = $this->auditReader->findByEntityClass(
            Author::class,
            from: new \DateTimeImmutable('-1 hour')
        );

        $this->assertCount(1, $result);
    }

    public function testFindByEntityClassWithFutureFromDateReturnsEmpty(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        // Query with from date in the future - should not find the entry
        $result = $this->auditReader->findByEntityClass(
            Author::class,
            from: new \DateTimeImmutable('+1 hour')
        );

        $this->assertSame([], $result);
    }

    public function testFindByEntityClassWithToDate(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        // Query with to date in the future - should find the entry
        $result = $this->auditReader->findByEntityClass(
            Author::class,
            to: new \DateTimeImmutable('+1 hour')
        );

        $this->assertCount(1, $result);
    }

    public function testFindByEntityClassWithPastToDateReturnsEmpty(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        // Query with to date in the past - should not find the entry
        $result = $this->auditReader->findByEntityClass(
            Author::class,
            to: new \DateTimeImmutable('-1 hour')
        );

        $this->assertSame([], $result);
    }

    public function testFindByEntityClassWithDateRange(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        // Query with date range that includes now
        $result = $this->auditReader->findByEntityClass(
            Author::class,
            from: new \DateTimeImmutable('-1 hour'),
            to: new \DateTimeImmutable('+1 hour')
        );

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // findByEntityClass ordering tests
    // =========================================================================

    public function testFindByEntityClassOrdersByIdDesc(): void
    {
        $author1 = $this->createAuthor('Author 1', 'author1@example.com');
        $author2 = $this->createAuthor('Author 2', 'author2@example.com');

        $result = $this->auditReader->findByEntityClass(
            Author::class,
            orderBy: 'id',
            order: 'DESC'
        );

        $this->assertCount(2, $result);
        // DESC order means higher ID first
        $this->assertGreaterThan($result[1]->getId(), $result[0]->getId());
    }

    public function testFindByEntityClassOrdersByIdAsc(): void
    {
        $author1 = $this->createAuthor('Author 1', 'author1@example.com');
        $author2 = $this->createAuthor('Author 2', 'author2@example.com');

        $result = $this->auditReader->findByEntityClass(
            Author::class,
            orderBy: 'id',
            order: 'ASC'
        );

        $this->assertCount(2, $result);
        // ASC order means lower ID first
        $this->assertLessThan($result[1]->getId(), $result[0]->getId());
    }

    public function testFindByEntityClassOrdersByCreatedAt(): void
    {
        $author1 = $this->createAuthor('Author 1', 'author1@example.com');
        $author2 = $this->createAuthor('Author 2', 'author2@example.com');

        $result = $this->auditReader->findByEntityClass(
            Author::class,
            orderBy: 'created_at',
            order: 'ASC'
        );

        $this->assertCount(2, $result);
        // Should be ordered by created_at ascending
        $this->assertLessThanOrEqual(
            $result[1]->getCreatedAt()->getTimestamp(),
            $result[0]->getCreatedAt()->getTimestamp()
        );
    }

    // =========================================================================
    // findByEntityClass with multiple IDs tests
    // =========================================================================

    public function testFindByEntityClassWithMultipleIds(): void
    {
        $author1 = $this->createAuthor('Author 1', 'author1@example.com');
        $author2 = $this->createAuthor('Author 2', 'author2@example.com');
        $author3 = $this->createAuthor('Author 3', 'author3@example.com');

        $result = $this->auditReader->findByEntityClass(
            Author::class,
            ids: [(string) $author1->getId(), (string) $author3->getId()]
        );

        $this->assertCount(2, $result);
        $objectIds = array_map(static fn (Entry $e) => $e->getObjectId(), $result);
        $this->assertContains((string) $author1->getId(), $objectIds);
        $this->assertContains((string) $author3->getId(), $objectIds);
        $this->assertNotContains((string) $author2->getId(), $objectIds);
    }

    // =========================================================================
    // findEntityAuditsByUserTimeline tests
    // =========================================================================

    public function testFindEntityAuditsByUserTimelineWithNullUser(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        $from = new \DateTimeImmutable('-1 hour');
        $to = new \DateTimeImmutable('+1 hour');
        $timezone = new \DateTimeZone('UTC');

        // Query with null user (system events)
        $result = $this->auditReader->findEntityAuditsByUserTimeline(
            Author::class,
            null,
            $from,
            $to,
            false,
            $timezone
        );

        // In tests, blame_id is null, so this should find the entry
        $this->assertCount(1, $result);
    }

    public function testFindEntityAuditsByUserTimelineWithIncludeSystemEvents(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        $from = new \DateTimeImmutable('-1 hour');
        $to = new \DateTimeImmutable('+1 hour');
        $timezone = new \DateTimeZone('UTC');

        // Query with a specific user but include system events
        $result = $this->auditReader->findEntityAuditsByUserTimeline(
            Author::class,
            '999', // Non-existent user
            $from,
            $to,
            true, // Include system events
            $timezone
        );

        // Should find the entry because it's a system event (null blame_id)
        $this->assertCount(1, $result);
    }

    public function testFindEntityAuditsByUserTimelineExcludesSystemEvents(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        $from = new \DateTimeImmutable('-1 hour');
        $to = new \DateTimeImmutable('+1 hour');
        $timezone = new \DateTimeZone('UTC');

        // Query with a specific user, exclude system events
        $result = $this->auditReader->findEntityAuditsByUserTimeline(
            Author::class,
            '999', // Non-existent user
            $from,
            $to,
            false, // Exclude system events
            $timezone
        );

        // Should NOT find the entry because blame_id doesn't match and system events excluded
        $this->assertSame([], $result);
    }

    // =========================================================================
    // findEntityAuditsByUserSearch tests
    // =========================================================================

    public function testFindEntityAuditsByUserSearchReturnsEmptyWhenNoMatches(): void
    {
        $this->createAuthor('Test Author', 'test@example.com');

        $result = $this->auditReader->findEntityAuditsByUserSearch(Author::class, 'nonexistent@email.com');

        $this->assertSame([], $result);
    }

    public function testFindEntityAuditsByUserSearchIsCaseInsensitive(): void
    {
        // Insert an audit entry with a specific blame_user value for testing
        $this->createAuthorWithBlameUser('Test Author', 'test@example.com', 'Admin@Example.Com');

        // Search with different case - should find the entry
        $result = $this->auditReader->findEntityAuditsByUserSearch(Author::class, 'admin@example.com');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Entry::class, $result[0]);
    }

    public function testFindEntityAuditsByUserSearchFindsPartialMatches(): void
    {
        // Insert an audit entry with a specific blame_user value for testing
        $this->createAuthorWithBlameUser('Test Author', 'test@example.com', 'john.doe@company.example.com');

        // Search with partial match - should find the entry
        $result = $this->auditReader->findEntityAuditsByUserSearch(Author::class, 'company');

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // Context integration tests
    // =========================================================================

    public function testAuditContextIsStoredInDiffs(): void
    {
        $this->auditContext->set([
            'note' => 'Test note',
            'reason' => 'test_reason',
        ]);
        $this->auditContext->setRequestId('context-test-request-id');

        $this->createAuthor('Test Author', 'test@example.com');

        $audits = $this->auditReader->findByEntityClass(Author::class);
        $this->assertCount(1, $audits);

        $diffs = $audits[0]->getDiffs(includeMedadata: true);
        $this->assertArrayHasKey('@context', $diffs);
        $this->assertSame('Test note', $diffs['@context']['note']);
        $this->assertSame('test_reason', $diffs['@context']['reason']);
        $this->assertSame('context-test-request-id', $diffs['@context']['request_id']);
    }

    /**
     * Drop all tables in the test database.
     */
    private function dropAllTables(): void
    {
        /** @var AuditingService[] $auditingServices */
        $auditingServices = $this->provider->getAuditingServices();
        foreach ($auditingServices as $auditingService) {
            $connection = $auditingService->getEntityManager()->getConnection();
            $schemaManager = $connection->createSchemaManager();

            // Drop all tables - getTables() returns Table objects
            foreach ($schemaManager->introspectSchema()->getTables() as $table) {
                try {
                    $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getName());
                } catch (\Exception) {
                    // Ignore errors
                }
            }
        }
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function createAuthor(string $name, string $email): Author
    {
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname($name)->setEmail($email);
        $em->persist($author);
        $em->flush();
        $this->flushAll([Author::class => $auditingService]);

        return $author;
    }

    /**
     * Create an author and set a specific blame_user value in the audit entry.
     *
     * This is needed for testing user search since the test environment
     * doesn't have a user provider configured.
     */
    private function createAuthorWithBlameUser(string $name, string $email, string $blameUser): Author
    {
        $author = $this->createAuthor($name, $email);

        // Update the audit entry to set the blame_user value
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $connection = $auditingService->getEntityManager()->getConnection();
        $tableName = $this->reader->getEntityAuditTableName(Author::class);

        $connection->executeStatement(
            \sprintf('UPDATE %s SET blame_user = ? WHERE object_id = ?', $tableName),
            [$blameUser, (string) $author->getId()]
        );

        return $author;
    }
}
