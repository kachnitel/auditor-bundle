<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use DH\Auditor\Model\Entry;
use Kachnitel\AuditorBundle\Admin\AuditDataSource;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Row actions for audit list view - user/request filter buttons.
 */
#[AsTwigComponent('K:Audit:RowActions', template: '@KachnitelAuditor/components/Audit/RowActions.html.twig')]
final class RowActions
{
    public Entry $item;

    public AuditDataSource $dataSource;
}
