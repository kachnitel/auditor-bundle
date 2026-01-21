<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Event;

use Kachnitel\AuditorBundle\Service\EventAuditService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Abstract subscriber for logging domain events to the audit trail.
 *
 * Extend this class and implement getSubscribedEvents() to list your concrete event classes:
 *
 *     final class EntityAuditSubscriber extends AbstractAuditableEventSubscriber
 *     {
 *         public static function getSubscribedEvents(): array
 *         {
 *             return [
 *                 OrderCreatedEvent::class => 'onAuditableEvent',
 *                 TaskCompletedEvent::class => 'onAuditableEvent',
 *             ];
 *         }
 *     }
 */
abstract class AbstractAuditableEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EventAuditService $eventAuditService) {}

    /**
     * Handle an auditable event by logging it to the audit trail.
     */
    public function onAuditableEvent(AuditableEventInterface $event): void
    {
        $data = $event->getData();

        if ($event instanceof AuditableEventWithChangesInterface) {
            $data['changes'] = $event->getChanges();
        }

        $this->eventAuditService->createEvent(
            $event->getEntity(),
            $event->getName(),
            $data
        );
    }
}
