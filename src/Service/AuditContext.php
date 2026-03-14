<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Service;

/**
 * Request-scoped service for passing contextual information to audits.
 *
 * ## Context hierarchy / first-wins
 *
 * In a single request, multiple services may try to set context. Only the
 * **first** caller wins — subsequent calls to set(), setNote(), and setReason()
 * are ignored once primary context has been claimed. This ensures the top-level
 * action (e.g. "user completed task") is always the visible reason, not a
 * side-effect deep in the call chain.
 *
 * Use override() when you intentionally need to replace the primary context.
 *
 * ## First-wins semantics per method
 *
 * - set()       : blocked once any primary context has been set (by set/setNote/setReason).
 *                 A full set() also blocks subsequent setNote()/setReason() calls, since
 *                 the batch context is considered fully owned by the first caller.
 * - setNote()   : blocked if a note key already exists, or if set() has been called.
 *                 Does NOT block setReason() — the two helpers are independent keys.
 * - setReason() : blocked if a reason key already exists, or if set() has been called.
 *                 Does NOT block setNote().
 * - setRequestId(): never blocked, never locks primary — infrastructure only.
 * - override()  : always replaces primary; preserves request_id.
 *
 * ## Usage
 *
 *   // Top-level caller claims context:
 *   $auditContext->set(['note' => 'User completed task', 'reason' => 'task_completion']);
 *
 *   // Deep in the call chain — silently ignored:
 *   $auditContext->set(['note' => 'Allocated to order']); // no-op
 *   $auditContext->setReason('side_effect');               // no-op (set() was called above)
 *
 *   // Explicit override when you mean it:
 *   $auditContext->override(['note' => 'Admin correction']);
 *
 *   // Helpers can be combined freely by the same caller:
 *   $auditContext->setNote('Task done');   // claims note
 *   $auditContext->setReason('completed'); // claims reason independently
 *   $auditContext->set(['note' => '...']); // no-op — helpers already set primary
 */
class AuditContext
{
    /** @var null|array<string, mixed> */
    private ?array $context = null;

    /**
     * True if set() or override() has been called.
     *
     * Blocks subsequent set(), setNote(), and setReason() calls — the batch
     * context is fully owned by the first caller who used set().
     */
    private bool $fullContextLocked = false;

    /**
     * True if any primary context has been established (set, setNote, setReason).
     *
     * Used exclusively to block subsequent set() calls from overwriting context
     * that was partially built up via the helper methods.
     */
    private bool $anyPrimarySet = false;

    /**
     * Claim the full primary context for this request.
     *
     * No-op if any primary context has already been claimed (by a prior set(),
     * setNote(), or setReason() call). Use override() to forcefully replace.
     *
     * Any request_id already present is automatically preserved.
     *
     * @param array<string, mixed> $context Key-value pairs to include in audit
     */
    public function set(array $context): self
    {
        if ($this->anyPrimarySet) {
            return $this;
        }

        $requestId = $this->getRequestId();

        $this->context = $context;

        if (null !== $requestId) {
            $this->context['request_id'] = $requestId;
        }

        if ([] !== $context) {
            $this->fullContextLocked = true;
            $this->anyPrimarySet = true;
        }

        return $this;
    }

    /**
     * Set the note for this audit entry.
     *
     * First-wins at the key level:
     * - No-op if the 'note' key is already set (by any method).
     * - No-op if set() has been called (full context is owned by that caller).
     * - Does NOT block setReason() — the two helpers are independent.
     *
     * Contributes to the primary lock, preventing a subsequent set() from
     * overwriting this context.
     */
    public function setNote(string $note): self
    {
        if ($this->fullContextLocked || isset($this->context['note'])) {
            return $this;
        }

        $this->context ??= [];
        $this->context['note'] = $note;
        $this->anyPrimarySet = true;

        return $this;
    }

    /**
     * Set the reason for this audit entry.
     *
     * First-wins at the key level:
     * - No-op if the 'reason' key is already set (by any method).
     * - No-op if set() has been called (full context is owned by that caller).
     * - Does NOT block setNote() — the two helpers are independent.
     *
     * Contributes to the primary lock, preventing a subsequent set() from
     * overwriting this context.
     */
    public function setReason(string $reason): self
    {
        if ($this->fullContextLocked || isset($this->context['reason'])) {
            return $this;
        }

        $this->context ??= [];
        $this->context['reason'] = $reason;
        $this->anyPrimarySet = true;

        return $this;
    }

    /**
     * Forcefully replace the primary context, regardless of whether one has
     * already been claimed.
     *
     * The existing request_id (if any) is preserved — it is infrastructure
     * and must not be lost on override.
     *
     * After override(), the context is locked again — subsequent set() calls
     * will be no-ops until clear() is called.
     *
     * @param array<string, mixed> $context
     */
    public function override(array $context): self
    {
        $requestId = $this->getRequestId();

        $this->context = $context;

        if (null !== $requestId) {
            $this->context['request_id'] = $requestId;
        }

        $this->fullContextLocked = true;
        $this->anyPrimarySet = true;

        return $this;
    }

    /**
     * Set the request ID for correlating audits from the same HTTP request.
     *
     * Infrastructure method — never blocked by primary context locks and never
     * participates in locking. Can be called at any point during the request.
     */
    public function setRequestId(string $requestId): self
    {
        $this->context ??= [];
        $this->context['request_id'] = $requestId;

        // Intentionally does NOT touch $fullContextLocked or $anyPrimarySet.

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
     * Get the full current context array, or null if nothing has been set.
     *
     * @return null|array<string, mixed>
     */
    public function get(): ?array
    {
        return $this->context;
    }

    /**
     * Whether any context is currently set (primary or request_id).
     */
    public function has(): bool
    {
        return null !== $this->context && [] !== $this->context;
    }

    /**
     * Whether a primary context has been claimed, locking out further set() calls.
     *
     * Useful for callers who want to know if they are the first setter or not.
     */
    public function hasPrimary(): bool
    {
        return $this->anyPrimarySet;
    }

    /**
     * Clear all context and release all locks.
     *
     * After calling clear(), set(), setNote(), and setReason() work again from
     * scratch. Normally called at end-of-request or in test setUp/tearDown.
     */
    public function clear(): void
    {
        $this->context = null;
        $this->fullContextLocked = false;
        $this->anyPrimarySet = false;
    }
}
