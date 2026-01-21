<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Service;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use DH\Auditor\User\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AuditorBundle\Service\AuditContext;
use Kachnitel\AuditorBundle\Service\EventAuditService;
use PHPUnit\Framework\Attributes\Small;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @internal
 */
#[Small]
final class EventAuditServiceTest extends KernelTestCase
{
    use BlogSchemaSetupTrait;
    use ReaderTrait;

    private DoctrineProvider $provider;
    private Auditor $auditor;
    private EntityManagerInterface $entityManager;
    private AuditContext $auditContext;
    private EventAuditService $eventAuditService;

    /** @var array<LifecycleEvent> */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $provider = self::getContainer()->get(DoctrineProvider::class);
        \assert($provider instanceof DoctrineProvider);
        $this->provider = $provider;

        $auditor = self::getContainer()->get(Auditor::class);
        \assert($auditor instanceof Auditor);
        $this->auditor = $auditor;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $auditContext = self::getContainer()->get(AuditContext::class);
        \assert($auditContext instanceof AuditContext);
        $this->auditContext = $auditContext;

        $this->configureEntities();
        $this->setupEntitySchemas();
        $this->setupAuditSchemas();

        $this->eventAuditService = new EventAuditService(
            $this->auditor,
            $this->provider,
            $this->entityManager,
            $this->auditContext
        );

        // Track dispatched events
        $this->dispatchedEvents = [];
        $this->auditor->getEventDispatcher()->addListener(
            LifecycleEvent::class,
            function (LifecycleEvent $event): void {
                $this->dispatchedEvents[] = $event;
            },
            // High priority to capture before persistence
            1000
        );
    }

    protected function tearDown(): void
    {
        $this->auditContext->clear();
        $this->dropAllTables();
        parent::tearDown();
    }

    public function testCreateEventDispatchesLifecycleEvent(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(LifecycleEvent::class, $this->dispatchedEvents[0]);
    }

    public function testCreateEventIncludesEventName(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.promoted', []);

        $payload = $this->dispatchedEvents[0]->getPayload();
        $diffs = json_decode($payload['diffs'], true);

        $this->assertArrayHasKey('@event', $diffs);
        $this->assertSame('author.promoted', $diffs['@event']);
    }

    public function testCreateEventIncludesData(): void
    {
        $author = $this->createAuthor();
        $eventData = [
            'role' => 'admin',
            'promotedBy' => 'system',
            'count' => 42,
        ];

        $this->eventAuditService->createEvent($author, 'author.promoted', $eventData);

        $payload = $this->dispatchedEvents[0]->getPayload();
        $diffs = json_decode($payload['diffs'], true);

        $this->assertSame('admin', $diffs['role']);
        $this->assertSame('system', $diffs['promotedBy']);
        $this->assertSame(42, $diffs['count']);
    }

    public function testCreateEventIncludesContextWhenSet(): void
    {
        $author = $this->createAuthor();
        $this->auditContext->set(['note' => 'Test context', 'reason' => 'manual']);

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();
        $diffs = json_decode($payload['diffs'], true);

        $this->assertArrayHasKey('@context', $diffs);
        $this->assertSame('Test context', $diffs['@context']['note']);
        $this->assertSame('manual', $diffs['@context']['reason']);
    }

    public function testCreateEventExcludesContextWhenNotSet(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();
        $diffs = json_decode($payload['diffs'], true);

        $this->assertArrayNotHasKey('@context', $diffs);
    }

    public function testCreateEventSetsCorrectPayloadType(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        $this->assertSame('event', $payload['type']);
    }

    public function testCreateEventSetsEntityClass(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        $this->assertSame(Author::class, $payload['entity']);
    }

    public function testCreateEventSetsObjectId(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        $this->assertSame((string) $author->getId(), $payload['object_id']);
    }

    public function testCreateEventSetsAuditTable(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        $this->assertSame('author_audit', $payload['table']);
    }

    public function testCreateEventSetsCreatedAt(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        $this->assertArrayHasKey('created_at', $payload);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$/',
            $payload['created_at']
        );
    }

    public function testCreateEventSetsTransactionHash(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        $this->assertArrayHasKey('transaction_hash', $payload);
        $this->assertNotEmpty($payload['transaction_hash']);
        // SHA1 hash is 40 characters
        $this->assertSame(40, \strlen($payload['transaction_hash']));
    }

    public function testCreateEventWithNoUser(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        // Without a configured user provider, blame fields should be null
        $this->assertNull($payload['blame_id']);
        $this->assertNull($payload['blame_user']);
        $this->assertNull($payload['blame_user_fqdn']);
    }

    public function testCreateEventWithUserInterface(): void
    {
        // Create a test user implementation
        $testUser = new class implements UserInterface {
            public function getIdentifier(): ?string
            {
                return '123';
            }

            public function getUsername(): ?string
            {
                return 'testuser';
            }
        };

        // Configure user provider
        $this->auditor->getConfiguration()->setUserProvider(static fn () => $testUser);
        $this->auditor->getConfiguration()->setSecurityProvider(static fn () => ['192.168.1.1', 'main']);

        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'author.activated', []);

        $payload = $this->dispatchedEvents[0]->getPayload();

        $this->assertSame('123', $payload['blame_id']);
        $this->assertSame('testuser', $payload['blame_user']);
        // Anonymous class name format varies by PHP version
        $this->assertStringContainsString('UserInterface@anonymous', $payload['blame_user_fqdn']);
        $this->assertSame('main', $payload['blame_user_firewall']);
        $this->assertSame('192.168.1.1', $payload['ip']);
    }

    public function testCreateEventWithEmptyData(): void
    {
        $author = $this->createAuthor();

        $this->eventAuditService->createEvent($author, 'simple.event', []);

        $payload = $this->dispatchedEvents[0]->getPayload();
        $diffs = json_decode($payload['diffs'], true);

        // Should only have @event key
        $this->assertCount(1, $diffs);
        $this->assertArrayHasKey('@event', $diffs);
    }

    private function createAuthor(): Author
    {
        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();

        $author = new Author();
        $author->setFullname('Test Author')->setEmail('test@example.com');
        $em->persist($author);
        $em->flush();

        // Clear the events from entity creation
        $this->dispatchedEvents = [];

        return $author;
    }

    private function dropAllTables(): void
    {
        $auditingServices = $this->provider->getAuditingServices();
        foreach ($auditingServices as $auditingService) {
            $connection = $auditingService->getEntityManager()->getConnection();
            $schemaManager = $connection->createSchemaManager();

            foreach ($schemaManager->introspectSchema()->getTables() as $table) {
                try {
                    $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getName());
                } catch (\Exception) {
                    // Ignore errors
                }
            }
        }
    }
}
