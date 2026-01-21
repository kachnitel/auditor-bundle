<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Event;

/**
 * Interface for domain events that include entity property changes.
 *
 * Use this for update events where you want to track what changed.
 * The changes will be stored in the audit entry under the 'changes' key.
 */
interface AuditableEventWithChangesInterface extends AuditableEventInterface
{
    /**
     * Property changes in the format: ['property' => ['old' => mixed, 'new' => mixed]].
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChanges(): array;
}
