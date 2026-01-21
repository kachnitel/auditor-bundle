<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Event;

use Kachnitel\AuditorBundle\Event\AbstractAuditableEventSubscriber;
use Kachnitel\AuditorBundle\Event\AuditableEventInterface;
use Kachnitel\AuditorBundle\Event\AuditableEventWithChangesInterface;
use Kachnitel\AuditorBundle\Service\EventAuditService;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AbstractAuditableEventSubscriberTest extends TestCase
{
    /** @var EventAuditService&MockObject */
    private EventAuditService $eventAuditService;

    private TestAuditableEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->eventAuditService = $this->createMock(EventAuditService::class);
        $this->subscriber = new TestAuditableEventSubscriber($this->eventAuditService);
    }

    public function testOnAuditableEventCreatesAuditEntry(): void
    {
        $entity = new \stdClass();
        $event = new TestAuditableEvent($entity, 'test.event', []);

        $this->eventAuditService->expects($this->once())
            ->method('createEvent')
            ->with($entity, 'test.event', []);

        $this->subscriber->onAuditableEvent($event);
    }

    public function testOnAuditableEventIncludesEventData(): void
    {
        $entity = new \stdClass();
        $data = ['key' => 'value', 'count' => 42];
        $event = new TestAuditableEvent($entity, 'data.event', $data);

        $this->eventAuditService->expects($this->once())
            ->method('createEvent')
            ->with($entity, 'data.event', $data);

        $this->subscriber->onAuditableEvent($event);
    }

    public function testOnAuditableEventIncludesChangesWhenInterface(): void
    {
        $entity = new \stdClass();
        $data = ['status' => 'updated'];
        $changes = [
            'name' => ['old' => 'Old Name', 'new' => 'New Name'],
            'price' => ['old' => 100, 'new' => 150],
        ];
        $event = new TestAuditableEventWithChanges($entity, 'entity.updated', $data, $changes);

        $expectedData = [
            'status' => 'updated',
            'changes' => $changes,
        ];

        $this->eventAuditService->expects($this->once())
            ->method('createEvent')
            ->with($entity, 'entity.updated', $expectedData);

        $this->subscriber->onAuditableEvent($event);
    }

    public function testOnAuditableEventDoesNotIncludeChangesForBasicInterface(): void
    {
        $entity = new \stdClass();
        $data = ['info' => 'test'];
        $event = new TestAuditableEvent($entity, 'basic.event', $data);

        $this->eventAuditService->expects($this->once())
            ->method('createEvent')
            ->with(
                $entity,
                'basic.event',
                $this->callback(function (array $passedData): bool {
                    return !\array_key_exists('changes', $passedData);
                })
            );

        $this->subscriber->onAuditableEvent($event);
    }
}

/**
 * Concrete implementation for testing.
 */
final class TestAuditableEventSubscriber extends AbstractAuditableEventSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            TestAuditableEvent::class => 'onAuditableEvent',
            TestAuditableEventWithChanges::class => 'onAuditableEvent',
        ];
    }
}

/**
 * Test event implementing AuditableEventInterface.
 */
final class TestAuditableEvent implements AuditableEventInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly object $entity,
        private readonly string $name,
        private readonly array $data
    ) {}

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }
}

/**
 * Test event implementing AuditableEventWithChangesInterface.
 */
final class TestAuditableEventWithChanges implements AuditableEventWithChangesInterface
{
    /**
     * @param array<string, mixed>                      $data
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    public function __construct(
        private readonly object $entity,
        private readonly string $name,
        private readonly array $data,
        private readonly array $changes
    ) {}

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }
}
