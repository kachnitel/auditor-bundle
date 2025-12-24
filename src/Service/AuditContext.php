<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Service;

/**
 * Request-scoped service for passing contextual information to audits.
 *
 * Usage:
 *   $auditContext->set(['note' => 'Manual stock adjustment', 'reason' => 'inventory_count']);
 *   $entity->setStock(100);
 *   $em->flush();
 *   // The context is automatically cleared after the audit is processed
 */
class AuditContext
{
    /** @var array<string, mixed>|null */
    private ?array $context = null;

    /**
     * Set the context that will be included in the next audit(s).
     *
     * @param array<string, mixed> $context Key-value pairs to include in audit
     */
    public function set(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Set a single context value.
     */
    public function setNote(string $note): self
    {
        $this->context ??= [];
        $this->context['note'] = $note;

        return $this;
    }

    /**
     * Set the reason for the change.
     */
    public function setReason(string $reason): self
    {
        $this->context ??= [];
        $this->context['reason'] = $reason;

        return $this;
    }

    /**
     * Get the current context, if any.
     *
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        return $this->context;
    }

    /**
     * Check if context is set.
     */
    public function has(): bool
    {
        return null !== $this->context && [] !== $this->context;
    }

    /**
     * Clear the context after use.
     */
    public function clear(): void
    {
        $this->context = null;
    }
}
