<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Service;

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
    /** @var null|array<string, mixed> */
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
     * Set the request ID for correlating audits from the same HTTP request.
     */
    public function setRequestId(string $requestId): self
    {
        $this->context ??= [];
        $this->context['request_id'] = $requestId;

        return $this;
    }

    /**
     * Get the current request ID, if set.
     */
    public function getRequestId(): ?string
    {
        $requestId = $this->context['request_id'] ?? null;

        return \is_string($requestId) ? $requestId : null;
    }

    /**
     * Get the current context, if any.
     *
     * @return null|array<string, mixed>
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
