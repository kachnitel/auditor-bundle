<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Event;

use DH\Auditor\Event\LifecycleEvent;
use Kachnitel\AuditorBundle\Event\AuditContextSubscriber;
use Kachnitel\AuditorBundle\Service\AuditContext;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditContextSubscriberTest extends TestCase
{
    private AuditContext $context;
    private AuditContextSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->context = new AuditContext();
        $this->subscriber = new AuditContextSubscriber($this->context);
    }

    public function testSubscribedEvents(): void
    {
        $events = AuditContextSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LifecycleEvent::class, $events);
        $this->assertSame([['onAuditEvent', -500_000]], $events[LifecycleEvent::class]);
    }

    public function testDoesNotModifyPayloadWhenNoContext(): void
    {
        $payload = $this->createValidPayload();
        $originalDiffs = $payload['diffs'];

        $event = new LifecycleEvent($payload);
        $this->subscriber->onAuditEvent($event);

        $this->assertSame($originalDiffs, $event->getPayload()['diffs']);
    }

    public function testInjectsContextIntoPayload(): void
    {
        $this->context->set(['note' => 'Test note', 'reason' => 'manual']);

        $payload = $this->createValidPayload();
        $event = new LifecycleEvent($payload);

        $this->subscriber->onAuditEvent($event);

        $modifiedPayload = $event->getPayload();
        $diffs = json_decode($modifiedPayload['diffs'], true);

        $this->assertArrayHasKey('@context', $diffs);
        $this->assertSame(['note' => 'Test note', 'reason' => 'manual'], $diffs['@context']);
    }

    public function testPreservesExistingDiffs(): void
    {
        $this->context->set(['note' => 'Test']);

        $originalDiffs = ['field1' => ['old' => 'a', 'new' => 'b']];
        $payload = $this->createValidPayload(json_encode($originalDiffs));

        $event = new LifecycleEvent($payload);
        $this->subscriber->onAuditEvent($event);

        $modifiedPayload = $event->getPayload();
        $diffs = json_decode($modifiedPayload['diffs'], true);

        $this->assertArrayHasKey('field1', $diffs);
        $this->assertSame(['old' => 'a', 'new' => 'b'], $diffs['field1']);
        $this->assertArrayHasKey('@context', $diffs);
    }

    private function createValidPayload(?string $diffs = null): array
    {
        return [
            'type' => 'update',
            'object_id' => '1',
            'discriminator' => null,
            'transaction_hash' => 'abc123',
            'diffs' => $diffs ?? '{}',
            'blame_id' => '1',
            'blame_user' => 'test',
            'blame_user_fqdn' => 'App\Entity\User',
            'blame_user_firewall' => 'main',
            'ip' => '127.0.0.1',
            'created_at' => '2024-01-01 00:00:00.000000',
        ];
    }
}
