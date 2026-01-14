<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Service;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for creating EVENT-type audit entries.
 *
 * EVENT audits represent domain events (like OrderCreated, TaskCompleted)
 * rather than direct entity changes tracked by Doctrine.
 *
 * Usage:
 *   $eventAuditService->createEvent($order, 'order.created', ['total' => 100.00]);
 */
class EventAuditService
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly DoctrineProvider $doctrineProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditContext $auditContext
    ) {}

    /**
     * Create an EVENT audit entry for an entity.
     *
     * @param object               $entity    The entity this event relates to
     * @param string               $eventName The name of the event (e.g., 'order.created')
     * @param array<string, mixed> $data      Additional event data
     */
    public function createEvent(object $entity, string $eventName, array $data = []): void
    {
        /** @var class-string<object> $className */
        $className = DoctrineHelper::getRealClassName($entity);
        $meta = $this->entityManager->getClassMetadata($className);

        // Build the diffs array with event data
        $diffs = [
            '@event' => $eventName,
            ...$data,
        ];

        // Include context if set
        if ($this->auditContext->has()) {
            $diffs['@context'] = $this->auditContext->get();
        }

        // Get entity ID
        $identifierValues = $meta->getIdentifierValues($entity);

        /** @var null|int|string $id */
        $id = reset($identifierValues) ?: null;

        // Get user/blame information from the configured provider
        $blame = $this->getBlame();

        $dt = new \DateTimeImmutable('now', new \DateTimeZone($this->auditor->getConfiguration()->getTimezone()));

        /** @var Configuration $configuration */
        $configuration = $this->doctrineProvider->getConfiguration();
        $auditTable = $configuration->getTablePrefix().$meta->getTableName().$configuration->getTableSuffix();

        $payload = [
            'entity' => $meta->getName(),
            'table' => $auditTable,
            'type' => 'event',
            'object_id' => (string) $id,
            'discriminator' => null,
            'transaction_hash' => $this->generateTransactionHash(),
            'diffs' => json_encode($diffs, JSON_THROW_ON_ERROR),
            'blame_id' => $blame['user_id'],
            'blame_user' => $blame['username'],
            'blame_user_fqdn' => $blame['user_fqdn'],
            'blame_user_firewall' => $blame['user_firewall'],
            'ip' => $blame['client_ip'],
            'created_at' => $dt->format('Y-m-d H:i:s.u'),
        ];

        // Dispatch the event - it will be persisted by AuditEventSubscriber
        $this->auditor->getEventDispatcher()->dispatch(new LifecycleEvent($payload));
    }

    /**
     * @return array{user_id: mixed, username: mixed, user_fqdn: mixed, user_firewall: mixed, client_ip: mixed}
     */
    private function getBlame(): array
    {
        $configuration = $this->auditor->getConfiguration();
        $userProvider = $configuration->getUserProvider();
        $securityProvider = $configuration->getSecurityProvider();

        $user = null !== $userProvider ? $userProvider() : null;
        $security = null !== $securityProvider ? $securityProvider() : [null, null];

        return [
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'user_fqdn' => $user['entity'] ?? null,
            'user_firewall' => $security[1] ?? null,
            'client_ip' => $security[0] ?? null,
        ];
    }

    private function generateTransactionHash(): string
    {
        return sha1(uniqid('event_', true));
    }
}
