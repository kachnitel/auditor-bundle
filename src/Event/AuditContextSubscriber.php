<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Event;

use DH\Auditor\Event\LifecycleEvent;
use DH\AuditorBundle\Service\AuditContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects AuditContext into audit payloads before they are persisted.
 *
 * The context is stored within the 'diffs' JSON field under a '@context' key
 * to distinguish it from regular diff data.
 */
class AuditContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditContext $context
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LifecycleEvent::class => [
                // Run before AuditEventSubscriber which has priority -1_000_000
                ['onAuditEvent', -500_000],
            ],
        ];
    }

    public function onAuditEvent(LifecycleEvent $event): void
    {
        if (!$this->context->has()) {
            return;
        }

        $payload = $event->getPayload();

        // Decode existing diffs, add context, re-encode
        $diffs = json_decode($payload['diffs'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        // Store context under @context key to avoid collision with field names
        $diffs['@context'] = $this->context->get();

        $payload['diffs'] = json_encode($diffs, JSON_THROW_ON_ERROR);
        $event->setPayload($payload);

        // Clear context after applying (context applies to single transaction)
        // Note: We don't clear immediately as there might be multiple entities in one flush
        // The context will be cleared at the end of the request or manually
    }
}
