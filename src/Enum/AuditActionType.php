<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Enum;

use DH\Auditor\Transaction\TransactionManager;

/**
 * Audit action types as defined by damienharper/auditor.
 *
 * @see TransactionManager for the original constants
 */
enum AuditActionType: string
{
    case INSERT = 'insert';
    case UPDATE = 'update';
    case REMOVE = 'remove';
    case ASSOCIATE = 'associate';
    case DISSOCIATE = 'dissociate';
    case EVENT = 'event';
}
