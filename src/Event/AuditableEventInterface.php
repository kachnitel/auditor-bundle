<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Event;

/**
 * Interface for domain events that should be logged to the audit trail.
 *
 * Implement this interface on your domain events (OrderCreated, TaskCompleted, etc.)
 * to enable automatic audit logging via AbstractAuditableEventSubscriber.
 */
interface AuditableEventInterface
{
    /**
     * The entity this event relates to.
     */
    public function getEntity(): object;

    /**
     * The event name (e.g., 'order.created', 'task.completed').
     */
    public function getName(): string;

    /**
     * Additional event data to store in the audit entry.
     *
     * @return array<string, mixed>
     */
    public function getData(): array;
}
